<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;

class TeamCymruAsnService {

    public function __construct(
        private readonly DnsLookupService $dnsLookupService = new DnsLookupService()
    ) {
    }

    public function lookup(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $query = $this->queryName($target->normalizedValue);

        if ($query === null) {
            return [
                'found' => false,
                'error' => 'IP inválido para consulta Team Cymru ASN.',
            ];
        }

        $timeoutSeconds = $this->timeoutSeconds($integration);
        $result = $this->dnsLookupService->txt($query, $timeoutSeconds);

        if (empty($result['records'])) {
            return [
                'found' => false,
                'error' => $result['error'] ?? 'Team Cymru ASN não retornou registros.',
            ];
        }

        $records = array_values(array_filter(array_map(
            fn(string $record) => $this->parseRecord($record),
            $result['records']
        )));

        if (empty($records)) {
            return [
                'found' => false,
                'error' => 'Resposta Team Cymru ASN em formato inesperado.',
            ];
        }

        $primary = $records[0];

        return [
            'found' => true,
            'data' => [
                'ip' => $target->normalizedValue,
                'query' => $query,
                'source' => 'Team Cymru IP to ASN DNS',
                'nameserver' => $result['nameserver'] ?? null,
                'timeoutSeconds' => $timeoutSeconds,
                'records' => $records,
            ],
            'summary' => [
                'asn' => $primary['asn'],
                'prefix' => $primary['prefix'],
                'countryCode' => $primary['countryCode'],
                'registry' => $primary['registry'],
                'allocatedAt' => $primary['allocatedAt'],
            ],
        ];
    }

    private function queryName(string $ip): ?string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip))) . '.origin.asn.cymru.com';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return null;
            }

            $hex = unpack('H*', $packed)[1];

            return implode('.', array_reverse(str_split($hex))) . '.origin6.asn.cymru.com';
        }

        return null;
    }

    private function timeoutSeconds(EnrichmentIntegration $integration): float {
        $configured = $integration->config?->getValue()['timeoutSeconds'] ?? null;

        if (is_numeric($configured) && (float)$configured > 0) {
            return max(0.2, min((float)$configured, 10.0));
        }

        return 3.0;
    }

    private function parseRecord(string $record): ?array {
        $parts = array_map('trim', explode('|', $record));

        if (count($parts) < 5) {
            return null;
        }

        return [
            'asn' => $parts[0] === 'NA' ? null : (int)$parts[0],
            'prefix' => $parts[1] ?: null,
            'countryCode' => $parts[2] ?: null,
            'registry' => $parts[3] ?: null,
            'allocatedAt' => $parts[4] ?: null,
            'raw' => $record,
        ];
    }

}
