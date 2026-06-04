<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;

class AbuseIpDbService {

    public function __construct(
        private readonly HttpJsonClientService $httpClient = new HttpJsonClientService()
    ) {
    }

    public function lookup(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $config = $integration->config?->getValue() ?? [];
        $baseUrl = rtrim((string)($config['baseUrl'] ?? ''), '/') ?: 'https://api.abuseipdb.com/api/v2';
        $timeoutSeconds = $this->timeoutSeconds($integration);
        $maxAgeInDays = $this->maxAgeInDays($integration);
        $query = [
            'ipAddress' => $target->normalizedValue,
            'maxAgeInDays' => $maxAgeInDays,
        ];

        if (!empty($config['verbose'])) {
            $query['verbose'] = '';
        }

        $response = $this->httpClient->get(
            $baseUrl . '/check',
            $query,
            [
                'Accept' => 'application/json',
                'Key' => (string)($config['apiKey'] ?? ''),
            ],
            $timeoutSeconds
        );

        if (!$response['ok']) {
            return [
                'found' => false,
                'executed' => $response['executed'],
                'error' => $this->errorMessage($response),
            ];
        }

        $data = $response['data']['data'] ?? null;

        if (!is_array($data)) {
            return [
                'found' => false,
                'executed' => true,
                'error' => 'AbuseIPDB retornou resposta sem campo data.',
            ];
        }

        return [
            'found' => true,
            'executed' => true,
            'data' => [
                ...$data,
                '_meta' => [
                    'source' => 'AbuseIPDB API v2 Check',
                    'statusCode' => $response['statusCode'],
                    'maxAgeInDays' => $maxAgeInDays,
                    'timeoutSeconds' => $timeoutSeconds,
                    'rateLimit' => $this->rateLimitHeaders($response['headers'] ?? []),
                ],
            ],
            'summary' => [
                'abuseConfidenceScore' => $data['abuseConfidenceScore'] ?? null,
                'totalReports' => $data['totalReports'] ?? null,
                'isWhitelisted' => $data['isWhitelisted'] ?? null,
                'countryCode' => $data['countryCode'] ?? null,
                'usageType' => $data['usageType'] ?? null,
                'isp' => $data['isp'] ?? null,
                'domain' => $data['domain'] ?? null,
                'lastReportedAt' => $data['lastReportedAt'] ?? null,
            ],
        ];
    }

    private function timeoutSeconds(EnrichmentIntegration $integration): float {
        $configured = $integration->config?->getValue()['timeoutSeconds'] ?? null;

        if (is_numeric($configured) && (float)$configured > 0) {
            return max(0.5, min((float)$configured, 30.0));
        }

        return 8.0;
    }

    private function maxAgeInDays(EnrichmentIntegration $integration): int {
        $configured = $integration->config?->getValue()['maxAgeInDays'] ?? null;

        if (is_numeric($configured)) {
            return max(1, min((int)$configured, 365));
        }

        return 90;
    }

    private function errorMessage(array $response): string {
        $errors = $response['data']['errors'] ?? [];

        if (!empty($errors[0]['detail'])) {
            return (string)$errors[0]['detail'];
        }

        return 'Erro AbuseIPDB HTTP ' . ($response['statusCode'] ?? 0) . '.';
    }

    private function rateLimitHeaders(array $headers): array {
        return array_filter([
            'limit' => $headers['x-ratelimit-limit'] ?? null,
            'remaining' => $headers['x-ratelimit-remaining'] ?? null,
            'reset' => $headers['x-ratelimit-reset'] ?? null,
        ], fn($value) => $value !== null);
    }

}
