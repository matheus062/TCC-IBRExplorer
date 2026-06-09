<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Service\EntityService;

class EnrichmentIntegrationService extends EntityService {

    public function __construct() {
        parent::__construct(EnrichmentIntegration::class);
    }

    public function update(int $id, Entity|array $data): bool {
        if (is_array($data)) {
            $current = $this->getById($id, ['*']);

            if ($current instanceof EnrichmentIntegration) {
                $data = $this->prepareUpdateData($data, $current);
            }
        }

        return parent::update($id, $data);
    }

    private function prepareUpdateData(array $body, EnrichmentIntegration $current): array {
        if (empty($body['configSchema'])) {
            $body['configSchema'] = $current->configSchema?->getValue() ?? [];
        }

        $config = $body['config'] ?? null;

        if (empty($config) || !is_array($config)) {
            return $body;
        }

        $schema = $body['configSchema'];
        $currentConfig = $current->config?->getValue() ?? [];

        foreach ($schema as $fieldSchema) {
            $fieldName = $fieldSchema['name'] ?? null;

            if (empty($fieldName) || empty($fieldSchema['secret'])) {
                continue;
            }

            if (array_key_exists($fieldName, $config) && $config[$fieldName] === '********') {
                $body['config'][$fieldName] = $currentConfig[$fieldName] ?? '';
            }
        }

        return $body;
    }

}
