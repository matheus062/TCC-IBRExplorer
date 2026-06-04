<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;

class ShodanInternetDbService {

    public function __construct(
        private readonly HttpJsonClientService $httpClient = new HttpJsonClientService()
    ) {
    }

    public function lookup(EnrichmentIntegration $integration, EnrichmentTarget $target, bool $useApiKey = false): array {
        $config = $integration->config?->getValue() ?? [];

        if ($useApiKey && !empty($config['apiKey'])) {
            $hostApi = $this->lookupHostApi($integration, $target, (string)$config['apiKey']);

            if (!empty($hostApi['found'])) {
                return $hostApi;
            }

            $internetDb = $this->lookupInternetDb($integration, $target);
            $internetDb['paidError'] = $hostApi['error'] ?? 'Shodan Host API não retornou dados.';
            $internetDb['consumeLimit'] = true;

            return $internetDb;
        }

        return $this->lookupInternetDb($integration, $target);
    }

    private function lookupHostApi(EnrichmentIntegration $integration, EnrichmentTarget $target, string $apiKey): array {
        $baseUrl = rtrim((string)($integration->config?->getValue()['hostApiBaseUrl'] ?? ''), '/')
            ?: 'https://api.shodan.io';
        $timeoutSeconds = $this->timeoutSeconds($integration);
        $response = $this->httpClient->get(
            $baseUrl . '/shodan/host/' . rawurlencode($target->normalizedValue),
            ['key' => $apiKey],
            ['Accept' => 'application/json'],
            $timeoutSeconds
        );

        if (!$response['ok']) {
            return [
                'found' => false,
                'executed' => $response['executed'],
                'consumeLimit' => true,
                'error' => $this->hostApiErrorMessage($response),
            ];
        }

        $data = $response['data'] ?? [];

        if (empty($data)) {
            return [
                'found' => false,
                'executed' => true,
                'consumeLimit' => true,
                'error' => 'Shodan Host API retornou resposta vazia.',
            ];
        }

        $services = $data['data'] ?? [];

        return [
            'found' => true,
            'executed' => true,
            'consumeLimit' => true,
            'data' => [
                ...$data,
                '_meta' => [
                    'source' => 'Shodan Host API',
                    'statusCode' => $response['statusCode'],
                    'timeoutSeconds' => $timeoutSeconds,
                ],
            ],
            'summary' => [
                'ip' => $data['ip_str'] ?? $target->normalizedValue,
                'organization' => $data['org'] ?? null,
                'isp' => $data['isp'] ?? null,
                'asn' => $data['asn'] ?? null,
                'countryCode' => $data['country_code'] ?? null,
                'city' => $data['city'] ?? null,
                'hostnames' => $data['hostnames'] ?? [],
                'ports' => $data['ports'] ?? $this->servicePorts($services),
                'tags' => $data['tags'] ?? [],
                'vulnsCount' => count($data['vulns'] ?? []),
                'serviceCount' => is_array($services) ? count($services) : 0,
            ],
        ];
    }

    private function timeoutSeconds(EnrichmentIntegration $integration): float {
        $configured = $integration->config?->getValue()['timeoutSeconds'] ?? null;

        if (is_numeric($configured) && (float)$configured > 0) {
            return max(0.5, min((float)$configured, 30.0));
        }

        return 6.0;
    }

    private function hostApiErrorMessage(array $response): string {
        $data = $response['data'] ?? [];

        if (!empty($data['error'])) {
            return (string)$data['error'];
        }

        return 'Erro Shodan Host API HTTP ' . ($response['statusCode'] ?? 0) . '.';
    }

    private function servicePorts(array $services): array {
        $ports = [];

        foreach ($services as $service) {
            if (isset($service['port'])) {
                $ports[] = (int)$service['port'];
            }
        }

        sort($ports);

        return array_values(array_unique($ports));
    }

    private function lookupInternetDb(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $baseUrl = rtrim((string)($integration->config?->getValue()['baseUrl'] ?? ''), '/')
            ?: 'https://internetdb.shodan.io';
        $timeoutSeconds = $this->timeoutSeconds($integration);
        $response = $this->httpClient->get(
            $baseUrl . '/' . rawurlencode($target->normalizedValue),
            headers: ['Accept' => 'application/json'],
            timeoutSeconds: $timeoutSeconds
        );

        if (!$response['ok']) {
            return [
                'found' => false,
                'executed' => $response['executed'],
                'consumeLimit' => false,
                'error' => $this->errorMessage($response),
            ];
        }

        $data = $response['data'] ?? [];

        if (empty($data)) {
            return [
                'found' => false,
                'executed' => true,
                'consumeLimit' => false,
                'error' => 'Shodan InternetDB retornou resposta vazia.',
            ];
        }

        return [
            'found' => true,
            'executed' => true,
            'consumeLimit' => false,
            'data' => [
                ...$data,
                '_meta' => [
                    'source' => 'Shodan InternetDB',
                    'statusCode' => $response['statusCode'],
                    'timeoutSeconds' => $timeoutSeconds,
                ],
            ],
            'summary' => [
                'ports' => $data['ports'] ?? [],
                'hostnames' => $data['hostnames'] ?? [],
                'tags' => $data['tags'] ?? [],
                'vulnsCount' => count($data['vulns'] ?? []),
                'cpesCount' => count($data['cpes'] ?? []),
            ],
        ];
    }

    private function errorMessage(array $response): string {
        $data = $response['data'] ?? [];

        if (($response['statusCode'] ?? null) === 404) {
            return 'Shodan InternetDB não possui dados para o IP.';
        }

        if (!empty($data['error'])) {
            return (string)$data['error'];
        }

        return 'Erro Shodan InternetDB HTTP ' . ($response['statusCode'] ?? 0) . '.';
    }

}
