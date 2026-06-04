<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

class DnsLookupService {

    private const int TYPE_PTR = 12;
    private const int TYPE_TXT = 16;
    private const int CLASS_IN = 1;

    public function txt(string $name, float $timeoutSeconds = 1.5, ?array $nameservers = null): array {
        return $this->query($name, self::TYPE_TXT, $timeoutSeconds, $nameservers);
    }

    private function query(string $name, int $type, float $timeoutSeconds, ?array $nameservers = null): array {
        $lastError = null;

        foreach ($this->nameservers($nameservers) as $nameserver) {
            $response = $this->sendQuery($nameserver, $name, $type, $timeoutSeconds);

            if (!empty($response['error'])) {
                $lastError = $response['error'];
                continue;
            }

            $records = $this->parseResponse($response['packet'], $type);

            if (!empty($records)) {
                return [
                    'records' => $records,
                    'error' => null,
                    'nameserver' => $nameserver,
                ];
            }

            $lastError = 'Consulta DNS sem registros.';
        }

        return [
            'records' => [],
            'error' => $lastError ?? 'Nenhum servidor DNS disponível.',
        ];
    }

    private function nameservers(?array $configuredNameservers = null): array {
        if (!empty($configuredNameservers)) {
            return array_values(array_unique(array_filter(array_map('trim', $configuredNameservers))));
        }

        $nameservers = [];
        $resolvConf = @file('/etc/resolv.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($resolvConf as $line) {
            if (!preg_match('/^nameserver\s+(\S+)/', trim($line), $matches)) {
                continue;
            }

            $nameservers[] = $matches[1];
        }

        return array_values(array_unique($nameservers ?: ['1.1.1.1', '8.8.8.8']));
    }

    private function sendQuery(string $nameserver, string $name, int $type, float $timeoutSeconds): array {
        $address = $this->dnsAddress($nameserver);
        $socket = @stream_socket_client(
            $address,
            $errno,
            $error,
            $timeoutSeconds
        );

        if ($socket === false) {
            return [
                'packet' => null,
                'error' => 'Falha ao conectar no DNS ' . $nameserver . ': ' . ($error ?: $errno),
            ];
        }

        stream_set_timeout($socket, (int)$timeoutSeconds, (int)(($timeoutSeconds - (int)$timeoutSeconds) * 1000000));
        fwrite($socket, $this->buildQueryPacket($name, $type));
        $packet = fread($socket, 4096);
        fclose($socket);

        if ($packet === false || $packet === '') {
            return [
                'packet' => null,
                'error' => 'Timeout ou resposta vazia do DNS ' . $nameserver . '.',
            ];
        }

        return [
            'packet' => $packet,
            'error' => null,
        ];
    }

    private function dnsAddress(string $nameserver): string {
        if (str_contains($nameserver, ':')) {
            return 'udp://[' . $nameserver . ']:53';
        }

        return 'udp://' . $nameserver . ':53';
    }

    private function buildQueryPacket(string $name, int $type): string {
        /** @noinspection PhpUnhandledExceptionInspection */
        $id = random_int(0, 65535);
        $packet = pack('n6', $id, 0x0100, 1, 0, 0, 0);

        foreach (explode('.', trim($name, '.')) as $label) {
            $packet .= chr(strlen($label)) . $label;
        }

        return $packet . "\0" . pack('n2', $type, self::CLASS_IN);
    }

    private function parseResponse(string $packet, int $type): array {
        if (strlen($packet) < 12) {
            return [];
        }

        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', substr($packet, 0, 12));
        $rcode = $header['flags'] & 0x000F;

        if ($rcode !== 0) {
            return [];
        }

        $offset = 12;

        for ($i = 0; $i < $header['qdcount']; $i++) {
            $this->readName($packet, $offset);
            $offset += 4;
        }

        $records = [];

        for ($i = 0; $i < $header['ancount'] && $offset < strlen($packet); $i++) {
            $this->readName($packet, $offset);

            if ($offset + 10 > strlen($packet)) {
                break;
            }

            $answer = unpack('ntype/nclass/Nttl/nlength', substr($packet, $offset, 10));
            $offset += 10;
            $recordOffset = $offset;
            $offset += $answer['length'];

            if ($answer['class'] !== self::CLASS_IN || $answer['type'] !== $type) {
                continue;
            }

            if ($type === self::TYPE_PTR) {
                $records[] = $this->readName($packet, $recordOffset);
            } elseif ($type === self::TYPE_TXT) {
                $records[] = $this->readTxtRecord($packet, $recordOffset, $answer['length']);
            }
        }

        return array_values(array_filter($records));
    }

    private function readName(string $packet, int &$offset, int $depth = 0): string {
        if ($depth > 20) {
            return '';
        }

        $labels = [];
        $length = strlen($packet);

        while ($offset < $length) {
            $labelLength = ord($packet[$offset]);

            if (($labelLength & 0xC0) === 0xC0) {
                if ($offset + 1 >= $length) {
                    return implode('.', $labels);
                }

                $pointer = (($labelLength & 0x3F) << 8) | ord($packet[$offset + 1]);
                $offset += 2;
                $pointerOffset = $pointer;
                $suffix = $this->readName($packet, $pointerOffset, $depth + 1);

                if ($suffix !== '') {
                    $labels[] = $suffix;
                }

                return implode('.', $labels);
            }

            $offset++;

            if ($labelLength === 0) {
                break;
            }

            $labels[] = substr($packet, $offset, $labelLength);
            $offset += $labelLength;
        }

        return implode('.', $labels);
    }

    private function readTxtRecord(string $packet, int $offset, int $length): string {
        $parts = [];
        $end = $offset + $length;

        while ($offset < $end && $offset < strlen($packet)) {
            $partLength = ord($packet[$offset]);
            $offset++;
            $parts[] = substr($packet, $offset, $partLength);
            $offset += $partLength;
        }

        return implode('', $parts);
    }

    public function ptr(string $ip, float $timeoutSeconds = 1.5, ?array $nameservers = null): array {
        $name = $this->reversePointerName($ip);

        if ($name === null) {
            return [
                'records' => [],
                'error' => 'IP inválido para consulta PTR.',
            ];
        }

        return $this->query($name, self::TYPE_PTR, $timeoutSeconds, $nameservers);
    }

    private function reversePointerName(string $ip): ?string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return null;
            }

            $hex = unpack('H*', $packed)[1];

            return implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
        }

        return null;
    }

}
