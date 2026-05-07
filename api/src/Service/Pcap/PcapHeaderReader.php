<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use IBRExplorer\Entity\Enum\Pcap\PcapHeaderType;
use RuntimeException;

class PcapHeaderReader {

    public function read(string $filePath): array {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo para leitura do cabeçalho.');
        }

        try {
            $magicBytes = fread($handle, 4);

            if ($magicBytes === false || strlen($magicBytes) < 4) {
                throw new RuntimeException('Arquivo inválido ou vazio ao ler magic number.');
            }

            $magicHex = strtolower(bin2hex($magicBytes));

            return match ($magicHex) {
                'a1b2c3d4', 'd4c3b2a1', 'a1b23c4d', '4d3cb2a1' => $this->readPcapHeader($handle, $magicBytes, $magicHex),
                '0a0d0d0a' => $this->readPcapNgHeader($handle, $magicBytes),
                default => throw new RuntimeException('Formato de captura não suportado ou magic number desconhecido: ' . $magicHex),
            };
        } finally {
            fclose($handle);
        }
    }

    private function readPcapHeader($handle, string $magicBytes, string $magicHex): array {
        $remaining = fread($handle, 20);

        if ($remaining === false || strlen($remaining) < 20) {
            throw new RuntimeException('Cabeçalho PCAP incompleto.');
        }

        $headerBytes = $magicBytes . $remaining;
        $littleEndian = in_array($magicHex, ['d4c3b2a1', '4d3cb2a1'], true);

        return [
            'headerType' => PcapHeaderType::Pcap->value,
            'magicNumber' => (int)hexdec(bin2hex($magicBytes)),
            'versionMajor' => $this->readUint16(substr($headerBytes, 4, 2), $littleEndian),
            'versionMinor' => $this->readUint16(substr($headerBytes, 6, 2), $littleEndian),
            'linkType' => $this->readUint32(substr($headerBytes, 20, 4), $littleEndian),
            'snapLen' => $this->readUint32(substr($headerBytes, 16, 4), $littleEndian),
        ];
    }

    private function readUint16(string $bytes, bool $littleEndian): int {
        $unpacked = unpack($littleEndian ? 'vvalue' : 'nvalue', $bytes);

        return (int)$unpacked['value'];
    }

    private function readUint32(string $bytes, bool $littleEndian): int {
        $unpacked = unpack($littleEndian ? 'Vvalue' : 'Nvalue', $bytes);

        return (int)$unpacked['value'];
    }

    private function readPcapNgHeader($handle, string $blockTypeBytes): array {
        $blockTotalLengthBytes = fread($handle, 4);
        $byteOrderMagicBytes = fread($handle, 4);
        $versionBytes = fread($handle, 4);
        $sectionLengthBytes = fread($handle, 8);
        $closingLengthBytes = fread($handle, 4);

        if (
            $blockTotalLengthBytes === false || strlen($blockTotalLengthBytes) < 4 ||
            $byteOrderMagicBytes === false || strlen($byteOrderMagicBytes) < 4 ||
            $versionBytes === false || strlen($versionBytes) < 4 ||
            $sectionLengthBytes === false || strlen($sectionLengthBytes) < 8 ||
            $closingLengthBytes === false || strlen($closingLengthBytes) < 4
        ) {
            throw new RuntimeException('Section Header Block PCAPNG incompleto.');
        }

        $byteOrderMagicHex = strtolower(bin2hex($byteOrderMagicBytes));
        $littleEndian = match ($byteOrderMagicHex) {
            '1a2b3c4d' => false,
            '4d3c2b1a' => true,
            default => throw new RuntimeException('Byte-order magic PCAPNG inválido: ' . $byteOrderMagicHex),
        };

        $blockTotalLength = $this->readUint32($blockTotalLengthBytes, $littleEndian);

        if ($blockTotalLength < 28) {
            throw new RuntimeException('Section Header Block PCAPNG inválido.');
        }

        $remainingSectionBytes = $blockTotalLength - 28;

        if ($remainingSectionBytes > 0) {
            fseek($handle, $remainingSectionBytes, SEEK_CUR);
        }

        $interface = $this->readFirstInterfaceDescriptionBlock($handle, $littleEndian);

        return [
            'headerType' => PcapHeaderType::PcapNg->value,
            'magicNumber' => (int)hexdec(bin2hex($blockTypeBytes)),
            'versionMajor' => $this->readUint16(substr($versionBytes, 0, 2), $littleEndian),
            'versionMinor' => $this->readUint16(substr($versionBytes, 2, 2), $littleEndian),
            'linkType' => $interface['linkType'],
            'snapLen' => $interface['snapLen'],
        ];
    }

    private function readFirstInterfaceDescriptionBlock($handle, bool $littleEndian): array {
        while (!feof($handle)) {
            $blockTypeBytes = fread($handle, 4);
            $blockLengthBytes = fread($handle, 4);

            if (
                $blockTypeBytes === false || strlen($blockTypeBytes) < 4 ||
                $blockLengthBytes === false || strlen($blockLengthBytes) < 4
            ) {
                break;
            }

            $blockType = $this->readUint32($blockTypeBytes, $littleEndian);
            $blockTotalLength = $this->readUint32($blockLengthBytes, $littleEndian);

            if ($blockTotalLength < 12) {
                throw new RuntimeException('Bloco PCAPNG com tamanho inválido.');
            }

            $bodyLength = $blockTotalLength - 12;
            $body = $bodyLength > 0 ? fread($handle, $bodyLength) : '';
            $closingLengthBytes = fread($handle, 4);

            if (
                ($bodyLength > 0 && ($body === false || strlen($body) < $bodyLength)) ||
                $closingLengthBytes === false || strlen($closingLengthBytes) < 4
            ) {
                throw new RuntimeException('Bloco PCAPNG incompleto durante leitura da interface.');
            }

            if ($blockType !== 0x00000001) {
                continue;
            }

            if ($body === '' || strlen($body) < 8) {
                throw new RuntimeException('Interface Description Block PCAPNG inválido.');
            }

            return [
                'linkType' => $this->readUint16(substr($body, 0, 2), $littleEndian),
                'snapLen' => $this->readUint32(substr($body, 4, 4), $littleEndian),
            ];
        }

        throw new RuntimeException('Nenhum Interface Description Block foi encontrado no PCAPNG.');
    }

}
