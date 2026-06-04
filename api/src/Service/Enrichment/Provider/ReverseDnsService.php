<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;

class ReverseDnsService {

    public function __construct(
        private readonly DnsLookupService $dnsLookupService = new DnsLookupService()
    ) {
    }

    public function lookup(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $timeoutSeconds = $this->timeoutSeconds($integration);
        $result = $this->dnsLookupService->ptr(
            $target->normalizedValue,
            $timeoutSeconds,
            $this->configuredNameservers($integration)
        );

        if (empty($result['records'])) {
            return [
                'found' => false,
                'error' => $result['error'] ?? 'rDNS não retornou registros PTR.',
            ];
        }

        $hostnames = array_values(array_unique(array_map(
            fn(string $hostname) => rtrim(strtolower($hostname), '.'),
            $result['records']
        )));

        return [
            'found' => true,
            'data' => [
                'ip' => $target->normalizedValue,
                'source' => 'DNS PTR',
                'nameserver' => $result['nameserver'] ?? null,
                'timeoutSeconds' => $timeoutSeconds,
                'hostnames' => $hostnames,
            ],
            'summary' => [
                'hostname' => $hostnames[0] ?? null,
                'hostnames' => $hostnames,
            ],
        ];
    }

    private function timeoutSeconds(EnrichmentIntegration $integration): float {
        $configured = $integration->config?->getValue()['timeoutSeconds'] ?? null;

        if (is_numeric($configured) && (float)$configured > 0) {
            return max(0.2, min((float)$configured, 10.0));
        }

        return 2.0;
    }

    private function configuredNameservers(EnrichmentIntegration $integration): ?array {
        $nameserver = $integration->config?->getValue()['nameserver'] ?? null;

        if (empty($nameserver)) {
            return null;
        }

        return [(string)$nameserver];
    }

}
