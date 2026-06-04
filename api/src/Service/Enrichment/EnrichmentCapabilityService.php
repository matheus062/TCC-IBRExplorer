<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment;

use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentProviderType;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentTargetType;
use IBRExplorer\Entity\Enum\System\EntityStatus;

class EnrichmentCapabilityService {

    public function __construct(
        private readonly EnrichmentLimitService $limitService = new EnrichmentLimitService()
    ) {
    }

    public function capabilitiesForTargets(array $targets): array {
        $integrations = $this->listIntegrations();
        $capabilities = [];

        foreach ($targets as $target) {
            if (!$target instanceof EnrichmentTarget) {
                continue;
            }

            foreach ($integrations as $integration) {
                $capabilities[] = $this->capabilityForTarget($integration, $target);
            }
        }

        return $capabilities;
    }

    public function listIntegrations(): array {
        $service = IBRExplorerApi::getInstance()->getEntityService(EnrichmentIntegration::class);
        $list = $service->list(
            ['*'],
            ['entityStatus' => EntityStatus::Active],
            ['id ASC'],
            100
        );

        return $list['entities'] ?? [];
    }

    public function capabilityForTarget(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $supported = $this->supportsTargetType($integration->provider, $target->type);
        $configuration = $this->hasRequiredConfiguration($integration);
        $limit = ($integration->provider === EnrichmentProviderType::ShodanInternetDb)
            ? ['allowed' => true, 'reason' => null]
            : $this->limitService->canUse($integration);
        $capable = $integration->enabled && $supported && $configuration['configured'] && $limit['allowed'];

        return [
            'integration' => $integration,
            'target' => $target,
            'capable' => $capable,
            'alwaysExecute' => $integration->alwaysExecute,
            'reason' => $this->firstReason([
                !$integration->enabled ? 'Integração inativa.' : null,
                !$supported ? 'Tipo de alvo não suportado pela integração.' : null,
                !$configuration['configured'] ? $configuration['reason'] : null,
                !$limit['allowed'] ? $limit['reason'] : null,
            ]),
        ];
    }

    private function supportsTargetType(EnrichmentProviderType $provider, EnrichmentTargetType $targetType): bool {
        return match ($provider) {
            EnrichmentProviderType::MaxMindGeoLite2,
            EnrichmentProviderType::TeamCymruAsn,
            EnrichmentProviderType::ReverseDns,
            EnrichmentProviderType::ShodanInternetDb,
            EnrichmentProviderType::Censys,
            EnrichmentProviderType::AbuseIpDb => $targetType === EnrichmentTargetType::Ip,
        };
    }

    private function hasRequiredConfiguration(EnrichmentIntegration $integration): array {
        $schema = $integration->configSchema->getValue();
        $config = $integration->config?->getValue() ?? [];

        foreach ($schema as $field) {
            if (empty($field['required'])) {
                continue;
            }

            $name = $field['name'] ?? null;

            if (empty($name) || empty($config[$name])) {
                return [
                    'configured' => false,
                    'reason' => 'Configuração obrigatória ausente.',
                ];
            }
        }

        return [
            'configured' => true,
            'reason' => null,
        ];
    }

    private function firstReason(array $reasons): ?string {
        foreach ($reasons as $reason) {
            if (!empty($reason)) {
                return $reason;
            }
        }

        return null;
    }

}
