<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment;

use DateTime;
use Exception;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentResult;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentProviderType;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentStatus;
use IBRExplorer\Service\Enrichment\Provider\AbuseIpDbService;
use IBRExplorer\Service\Enrichment\Provider\CensysService;
use IBRExplorer\Service\Enrichment\Provider\MaxMindGeoLite2Service;
use IBRExplorer\Service\Enrichment\Provider\ReverseDnsService;
use IBRExplorer\Service\Enrichment\Provider\ShodanInternetDbService;
use IBRExplorer\Service\Enrichment\Provider\TeamCymruAsnService;

class EnrichmentExecutionService {

    public function __construct(
        private readonly EnrichmentCapabilityService $capabilityService = new EnrichmentCapabilityService(),
        private readonly EnrichmentLimitService      $limitService = new EnrichmentLimitService(),
        private readonly MaxMindGeoLite2Service      $maxMindGeoLite2Service = new MaxMindGeoLite2Service(),
        private readonly TeamCymruAsnService         $teamCymruAsnService = new TeamCymruAsnService(),
        private readonly ReverseDnsService           $reverseDnsService = new ReverseDnsService(),
        private readonly ShodanInternetDbService     $shodanInternetDbService = new ShodanInternetDbService(),
        private readonly AbuseIpDbService            $abuseIpDbService = new AbuseIpDbService(),
        private readonly CensysService               $censysService = new CensysService()
    ) {
    }

    public function execute(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $capability = $this->capabilityService->capabilityForTarget($integration, $target);

        if (!$capability['capable']) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $capability['reason'] ?? 'Integração não apta para execução.',
            ]);
        }

        return match ($integration->provider) {
            EnrichmentProviderType::MaxMindGeoLite2 => $this->executeMaxMindGeoLite2($integration, $target),
            EnrichmentProviderType::TeamCymruAsn => $this->executeTeamCymruAsn($integration, $target),
            EnrichmentProviderType::ReverseDns => $this->executeReverseDns($integration, $target),
            EnrichmentProviderType::ShodanInternetDb => $this->executeShodanInternetDb($integration, $target),
            EnrichmentProviderType::AbuseIpDb => $this->executeAbuseIpDb($integration, $target),
            EnrichmentProviderType::Censys => $this->executeCensys($integration, $target),
        };
    }

    private function saveResult(
        EnrichmentIntegration $integration,
        EnrichmentTarget      $target,
        array                 $result
    ): array {
        $service = IBRExplorerApi::getInstance()->getEntityService(EnrichmentResult::class);
        $existing = $service->list(
            ['id'],
            [
                'target' => $target->id,
                'integration' => $integration->id
            ],
            limit: 1
        );

        $payload = [
            'target' => $target->id,
            'integration' => $integration->id,
            'status' => $result['status']->value,
            'data' => $this->sanitizeData($integration, $result['data'] ?? []),
            'summary' => $result['summary'] ?? [],
            'confidence' => $result['confidence'] ?? null,
            'error' => $result['error'] ?? null,
            'fetchedAt' => (new DateTime())->format('Y-m-d H:i:s'),
            'expiresAt' => $result['expiresAt'] ?? null,
        ];

        $saved = empty($existing['entities'][0])
            ? $service->create($payload)
            : $service->update($existing['entities'][0]->id, $payload);

        if ($saved !== false && ($result['status'] === EnrichmentStatus::Success || !empty($result['consumeLimit']))) {
            try {
                $this->limitService->registerUse($integration);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'status' => $result['status']->value,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($saved !== false && $result['status'] === EnrichmentStatus::Success) {
            $this->updateTargetLastEnrichedAt($target);
        }

        return [
            'success' => $saved !== false,
            'status' => $result['status']->value,
            'error' => $saved === false ? $service->getError() : ($result['error'] ?? null),
        ];
    }

    private function sanitizeData(EnrichmentIntegration $integration, array $data): array {
        $fields = $integration->resultExcludedFields?->getValue() ?? [];

        foreach ($fields as $field) {
            $this->unsetPath($data, explode('.', (string)$field));
        }

        return $data;
    }

    private function unsetPath(array &$data, array $path): void {
        $key = array_shift($path);

        if ($key === null) {
            return;
        }

        if (!array_key_exists($key, $data)) {
            if ($this->isList($data)) {
                array_walk($data, function (&$item) use ($key, $path): void {
                    if (is_array($item)) {
                        $nestedPath = [$key, ...$path];
                        $this->unsetPath($item, $nestedPath);
                    }
                });
            }

            return;
        }

        if (empty($path)) {
            unset($data[$key]);

            return;
        }

        if (is_array($data[$key])) {
            $this->unsetPath($data[$key], $path);
        }
    }

    private function isList(array $data): bool {
        return array_keys($data) === range(0, count($data) - 1);
    }

    private function updateTargetLastEnrichedAt(EnrichmentTarget $target): void {
        $service = IBRExplorerApi::getInstance()->getEntityService(EnrichmentTarget::class);
        $service->update($target->id, [
            'lastEnrichedAt' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    private function executeMaxMindGeoLite2(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $lookup = $this->maxMindGeoLite2Service->lookupCity($integration, $target);

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'GeoLite2 City não retornou dados para o alvo.',
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
        ]);
    }

    private function executeTeamCymruAsn(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $lookup = $this->teamCymruAsnService->lookup($integration, $target);

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'Team Cymru ASN não retornou dados para o alvo.',
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
        ]);
    }

    private function executeReverseDns(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $lookup = $this->reverseDnsService->lookup($integration, $target);

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'rDNS não retornou dados para o alvo.',
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
        ]);
    }

    private function executeShodanInternetDb(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $limit = $this->limitService->canUse($integration);
        $lookup = $this->shodanInternetDbService->lookup(
            $integration,
            $target,
            $this->hasConfigValue($integration, 'apiKey') && $limit['allowed']
        );

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'Shodan InternetDB não retornou dados para o alvo.',
                'consumeLimit' => !empty($lookup['consumeLimit']),
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
            'consumeLimit' => !empty($lookup['consumeLimit']),
        ]);
    }

    private function hasConfigValue(EnrichmentIntegration $integration, string $field): bool {
        $config = $integration->config?->getValue() ?? [];

        return !empty($config[$field]);
    }

    private function executeAbuseIpDb(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $lookup = $this->abuseIpDbService->lookup($integration, $target);

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'AbuseIPDB não retornou dados para o alvo.',
                'consumeLimit' => !empty($lookup['executed']),
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
            'consumeLimit' => !empty($lookup['executed']),
        ]);
    }

    private function executeCensys(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $lookup = $this->censysService->lookup($integration, $target);

        if (empty($lookup['found'])) {
            return $this->saveResult($integration, $target, [
                'status' => EnrichmentStatus::Skipped,
                'error' => $lookup['error'] ?? 'Censys não retornou dados para o alvo.',
                'consumeLimit' => !empty($lookup['executed']),
            ]);
        }

        return $this->saveResult($integration, $target, [
            'status' => EnrichmentStatus::Success,
            'data' => $lookup['data'] ?? [],
            'summary' => $lookup['summary'] ?? [],
            'consumeLimit' => !empty($lookup['executed']),
        ]);
    }

}
