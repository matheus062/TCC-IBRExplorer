<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;

class CensysService {

    public function __construct(
        private readonly HttpJsonClientService $httpClient = new HttpJsonClientService()
    ) {
    }

    public function lookup(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $config = $integration->config?->getValue() ?? [];
        $baseUrl = rtrim((string)($config['baseUrl'] ?? ''), '/') ?: 'https://api.platform.censys.io/v3/global';
        $timeoutSeconds = $this->timeoutSeconds($integration);
        $response = $this->httpClient->postJson(
            $baseUrl . '/asset/host',
            ['host_ids' => [$target->normalizedValue]],
            headers: [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . ($config['apiKey'] ?? ''),
            ],
            timeoutSeconds: $timeoutSeconds
        );

        if (!$response['ok']) {
            return [
                'found' => false,
                'executed' => $response['executed'],
                'error' => $this->errorMessage($response),
            ];
        }

        $result = $response['data']['result'] ?? null;

        if (!is_array($result)) {
            return [
                'found' => false,
                'executed' => true,
                'error' => 'Censys retornou resposta sem campo result.',
            ];
        }

        $resource = $this->hostResource($result, $target->normalizedValue);
        $services = $resource['services'] ?? [];

        return [
            'found' => true,
            'executed' => true,
            'data' => [
                ...$resource,
                '_meta' => [
                    'source' => 'Censys Platform API v3 Global Host',
                    'statusCode' => $response['statusCode'],
                    'timeoutSeconds' => $timeoutSeconds,
                ],
            ],
            'summary' => [
                'ip' => $resource['ip'] ?? $target->normalizedValue,
                'name' => $resource['name'] ?? null,
                'location' => $resource['location'] ?? null,
                'autonomousSystem' => $resource['autonomous_system'] ?? null,
                'lastUpdatedAt' => $resource['last_updated_at'] ?? null,
                'serviceCount' => is_array($services) ? count($services) : 0,
                'ports' => $this->servicePorts($services),
            ],
        ];
    }

    private function timeoutSeconds(EnrichmentIntegration $integration): float {
        $configured = $integration->config?->getValue()['timeoutSeconds'] ?? null;

        if (is_numeric($configured) && (float)$configured > 0) {
            return max(0.5, min((float)$configured, 30.0));
        }

        return 10.0;
    }

    private function errorMessage(array $response): string {
        $data = $response['data'] ?? [];

        if (($response['statusCode'] ?? null) === 404) {
            return 'Censys não possui dados para o host.';
        }

        if (!empty($data['error'])) {
            return (string)$data['error'];
        }

        if (!empty($data['message'])) {
            return (string)$data['message'];
        }

        return 'Erro Censys HTTP ' . ($response['statusCode'] ?? 0) . '.';
    }

    private function hostResource(array $result, string $ip): array {
        // single host wrapper: result.resource
        if (!empty($result['resource']) && is_array($result['resource'])) {
            return $result['resource'];
        }

        // resources dict keyed by IP: result.resources.{ip}
        if (!empty($result['resources'][$ip]) && is_array($result['resources'][$ip])) {
            return $result['resources'][$ip];
        }

        // resources as indexed list: result.resources[0]
        if (!empty($result['resources'][0]) && is_array($result['resources'][0])) {
            $item = $result['resources'][0];
            return !empty($item['resource']) && is_array($item['resource']) ? $item['resource'] : $item;
        }

        // result IS the list of hosts — Censys Platform API v3 returns [{resource:{...}, extensions:{...}}]
        if (array_key_exists(0, $result) && is_array($result[0])) {
            $fallback = null;

            foreach ($result as $item) {
                if (!is_array($item)) {
                    continue;
                }

                // unwrap inner 'resource' key if present
                $candidate = !empty($item['resource']) && is_array($item['resource'])
                    ? $item['resource']
                    : $item;

                if (($candidate['ip'] ?? null) === $ip) {
                    return $candidate;
                }

                $fallback ??= $candidate;
            }

            return $fallback ?? $result[0];
        }

        return $result;
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

}
