<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use DateInvalidTimeZoneException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IBRExplorer\Entity\Enum\Pcap\PcapProtocolType;
use RuntimeException;

class TsharkPcapParser {

    private const string OUTPUT_SEPARATOR = '|';
    private const int STDERR_BUFFER_LIMIT_BYTES = 65536;

    private const array FIELD_ORDER = [
        'frame.number',
        'frame.time_epoch',
        'frame.file_off',
        'frame.cap_len',
        'frame.len',
        'ip.src',
        'ipv6.src',
        'ip.dst',
        'ipv6.dst',
        'tcp.srcport',
        'udp.srcport',
        'tcp.dstport',
        'udp.dstport',
        'ip.proto',
        'ipv6.nxt',
        'ip.version',
        'ipv6.version',
        'ip.ttl',
        'ipv6.hlim',
        'ip.len',
        'ipv6.plen',
        'tcp.len',
        'udp.length',
        'tcp.flags',
        'icmp.type',
        'icmp.code',
        'icmpv6.type',
        'icmpv6.code',
    ];

    public function __construct(
        private readonly string $binary = PCAP_TSHARK_BIN,
        private readonly int    $timeoutSeconds = PCAP_TSHARK_TIMEOUT_SECONDS,
    ) {
    }

    /**
     * @throws Exception
     */
    public function assertAvailable(): void {
        $process = proc_open(
            [$this->binary, '--version'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o processo do tshark.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (($exitCode !== 0) || !is_string($stdout) || $stdout === '') {
            throw new RuntimeException(
                'tshark indisponível no ambiente do worker.'
                . (!empty($stderr) ? ' ' . $stderr : '')
            );
        }
    }

    /**
     * @throws Exception
     */
    public function streamPackets(string $filePath, callable $onPacket): void {
        $process = proc_open(
            $this->buildCommand($filePath),
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o parsing com tshark.');
        }

        stream_set_timeout($pipes[1], $this->timeoutSeconds);
        stream_set_blocking($pipes[2], false);
        $parsedAnyPacket = false;
        $stderrBuffer = '';

        while (($line = fgets($pipes[1])) !== false) {
            $parsedAnyPacket = true;
            $packet = $this->parseLine(rtrim($line, "\r\n"));
            $stderrBuffer = $this->appendToLimitedBuffer($stderrBuffer, $this->readAvailableStream($pipes[2]));

            if ($packet !== null) {
                $onPacket($packet);
            }
        }

        $stdoutMetadata = stream_get_meta_data($pipes[1]);
        $stderrBuffer = $this->appendToLimitedBuffer($stderrBuffer, $this->readAvailableStream($pipes[2]));
        $stderr = trim($stderrBuffer);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (!empty($stdoutMetadata['timed_out'])) {
            throw new RuntimeException('Tempo limite excedido durante a leitura do tshark.');
        } elseif ($exitCode !== 0) {
            throw new RuntimeException(
                'Falha ao executar tshark.'
                . (!empty($stderr) ? ' ' . $stderr : '')
            );
        } elseif (!$parsedAnyPacket) {
            throw new RuntimeException('A captura não possui pacotes processáveis nesta etapa.');
        }
    }

    private function buildCommand(string $filePath): array {
        $command = [
            $this->binary,
            '-n',
            '-r',
            $filePath,
            '-o',
            'frame.show_file_off:TRUE',
            '-T',
            'fields',
            '-E',
            'header=n',
            '-E',
            'separator=' . self::OUTPUT_SEPARATOR,
            '-E',
            'quote=n',
            '-E',
            'occurrence=f',
        ];

        foreach (self::FIELD_ORDER as $field) {
            $command[] = '-e';
            $command[] = $field;
        }

        return $command;
    }

    /**
     * @throws DateInvalidTimeZoneException
     */
    private function parseLine(string $line): ?array {
        if ($line === '') {
            return null;
        }

        $parts = explode(self::OUTPUT_SEPARATOR, $line);

        if (count($parts) < count(self::FIELD_ORDER)) {
            $parts = array_pad($parts, count(self::FIELD_ORDER), '');
        }

        $fields = array_combine(self::FIELD_ORDER, $parts);

        if (empty($fields)) {
            throw new RuntimeException('Não foi possível mapear a saída do tshark.');
        }

        $protocolNumber = $this->normalizeProtocolValue(
            $this->firstNonEmpty($fields['ip.proto'], $fields['ipv6.nxt'])
        );
        $udpLength = $this->parseDecimalInt($fields['udp.length']);

        return [
            'packetNumber' => $this->parseRequiredInt($fields['frame.number'], 'frame.number'),
            'timestamp' => $this->parseEpochTimestamp($fields['frame.time_epoch']),
            'offset' => $this->parseRequiredInt($fields['frame.file_off'], 'frame.file_off'),
            'capturedLen' => $this->parseRequiredInt($fields['frame.cap_len'], 'frame.cap_len'),
            'originalLen' => $this->parseRequiredInt($fields['frame.len'], 'frame.len'),
            'srcIp' => $this->nullableString($this->firstNonEmpty($fields['ip.src'], $fields['ipv6.src'])),
            'dstIp' => $this->nullableString($this->firstNonEmpty($fields['ip.dst'], $fields['ipv6.dst'])),
            'srcPort' => $this->parseNullableInt($this->firstNonEmpty($fields['tcp.srcport'], $fields['udp.srcport'])),
            'dstPort' => $this->parseNullableInt($this->firstNonEmpty($fields['tcp.dstport'], $fields['udp.dstport'])),
            'protocol' => $protocolNumber,
            'protocolLabel' => $this->protocolLabel($protocolNumber),
            'ipVersion' => $this->parseNullableInt($this->firstNonEmpty($fields['ip.version'], $fields['ipv6.version'])),
            'ttl' => $this->parseNullableInt($this->firstNonEmpty($fields['ip.ttl'], $fields['ipv6.hlim'])),
            'ipLength' => $this->parseNullableInt($this->firstNonEmpty($fields['ip.len'], $fields['ipv6.plen'])),
            'payloadSize' => $this->parsePayloadSize($fields['tcp.len'], $udpLength),
            'tcpFlags' => $this->parseNullableInt($fields['tcp.flags'], true),
            'icmpType' => $this->parseNullableInt($this->firstNonEmpty($fields['icmp.type'], $fields['icmpv6.type'])),
            'icmpCode' => $this->parseNullableInt($this->firstNonEmpty($fields['icmp.code'], $fields['icmpv6.code'])),
        ];
    }

    private function normalizeProtocolValue(?string $value): ?int {
        $protocol = $this->parseNullableInt($value);

        if ($protocol === null) {
            return null;
        }

        return PcapProtocolType::tryFrom($protocol)?->value ?? PcapProtocolType::Other->value;
    }

    private function parseNullableInt(?string $value, bool $allowHex = false): ?int {
        if ($value === null || $value === '') {
            return null;
        } elseif ($allowHex && str_starts_with(strtolower($value), '0x')) {
            return (int)hexdec(substr($value, 2));
        }

        return $this->parseDecimalInt($value);
    }

    private function parseDecimalInt(?string $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? (int)$value
            : null;
    }

    private function firstNonEmpty(?string ...$values): ?string {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseRequiredInt(string $value, string $field): int {
        $parsed = $this->parseNullableInt($value);

        if ($parsed === null) {
            throw new RuntimeException('Campo obrigatório ausente na saída do tshark: ' . $field);
        }

        return $parsed;
    }

    /**
     * @throws DateInvalidTimeZoneException
     */
    private function parseEpochTimestamp(string $epoch): string {
        $epoch = trim($epoch);

        if ($epoch === '') {
            throw new RuntimeException('Timestamp vazio retornado pelo tshark.');
        }

        $normalized = str_replace(',', '.', $epoch);

        if (!preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new RuntimeException('Timestamp inválido retornado pelo tshark: ' . $epoch);
        }

        [$seconds, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $normalized = $seconds . '.' . str_pad(substr($fraction, 0, 6), 6, '0');

        $dateTime = DateTimeImmutable::createFromFormat('U.u', $normalized, new DateTimeZone('UTC'));

        if ($dateTime === false) {
            throw new RuntimeException('Timestamp inválido retornado pelo tshark: ' . $epoch);
        }

        return $dateTime
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s.u');
    }

    private function nullableString(?string $value): ?string {
        return ($value === null || $value === '')
            ? null
            : $value;
    }

    private function protocolLabel(?int $protocolValue): ?string {
        if ($protocolValue === null) {
            return null;
        }

        $protocol = PcapProtocolType::tryFrom($protocolValue) ?? PcapProtocolType::Other;

        return match ($protocol) {
            PcapProtocolType::Tcp => 'TCP',
            PcapProtocolType::Udp => 'UDP',
            PcapProtocolType::Icmp => 'ICMP',
            PcapProtocolType::Icmpv6 => 'ICMPV6',
            PcapProtocolType::Ipv4 => 'IPV4',
            PcapProtocolType::Ipv6 => 'IPV6',
            PcapProtocolType::Other => 'OTHER',
            default => strtoupper($protocol->name),
        };
    }

    private function parsePayloadSize(string $tcpLength, ?int $udpLength): ?int {
        $tcpPayload = $this->parseNullableInt($tcpLength);

        if ($tcpPayload !== null) {
            return $tcpPayload;
        } elseif ($udpLength !== null) {
            return max(0, $udpLength - 8);
        }

        return null;
    }

    private function appendToLimitedBuffer(string $buffer, string $chunk): string {
        if ($chunk === '') {
            return $buffer;
        }

        $buffer .= $chunk;

        if (strlen($buffer) <= self::STDERR_BUFFER_LIMIT_BYTES) {
            return $buffer;
        }

        return substr($buffer, -self::STDERR_BUFFER_LIMIT_BYTES);
    }

    private function readAvailableStream($stream): string {
        $buffer = '';

        while (is_resource($stream) && !feof($stream)) {
            $chunk = fread($stream, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;

            if (strlen($chunk) < 8192) {
                break;
            }
        }

        return $buffer;
    }

}
