<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enrichment;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentProviderType;
use IBRExplorer\Util\EncryptionService;
use IBRExplorer\Util\JsonField;
use IBRExplorer\Util\SimpleArray;
use Throwable;

class EnrichmentIntegration extends Entity {

    public EnrichmentProviderType $provider;
    public string $identifier;
    public string $name;
    public ?string $description;
    public bool $enabled;
    public bool $alwaysExecute;
    public bool $requiresApiKey;
    public JsonField $configSchema;
    public ?JsonField $config;
    public ?string $configSecrets;
    public ?SimpleArray $resultExcludedFields;
    public ?int $dailyLimit;
    public ?int $weeklyLimit;
    public ?int $monthlyLimit;
    public int $dailyUsed;
    public int $weeklyUsed;
    public int $monthlyUsed;
    public ?DateTime $dailyResetAt;
    public ?DateTime $weeklyResetAt;
    public ?DateTime $monthlyResetAt;
    public ?DateTime $lastUsedAt;
    public ?string $lastError;

    public function setData(array $data): void {
        parent::setData($data);

        if ($this->isNew()) {
            $this->enabled ??= false;
            $this->alwaysExecute ??= false;
            $this->requiresApiKey ??= false;
            $this->dailyUsed ??= 0;
            $this->weeklyUsed ??= 0;
            $this->monthlyUsed ??= 0;
        }

        $this->decryptSecrets();
    }

    private function decryptSecrets(): void {
        if (empty($this->configSecrets)) {
            $this->configSecrets = null;
            return;
        }

        try {
            $decrypted = EncryptionService::decrypt($this->configSecrets);
            $secrets = json_decode($decrypted, true) ?? [];

            if (!empty($secrets) && isset($this->config)) {
                $merged = array_merge($this->config->getValue(), $secrets);
                $merged_field = new JsonField();
                $merged_field->setValue($merged);
                $this->config = $merged_field;
            }
        } catch (Throwable) {
        }

        $this->configSecrets = null;
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'configSchema', 'config' => JsonField::class,
            'resultExcludedFields' => SimpleArray::class,
            default => parent::isValueObject($field)
        };
    }

    public function jsonSerialize(bool $database = false): array {
        $data = parent::jsonSerialize($database);

        if (!$database) {
            return $this->maskSecretsForFrontend($data);
        }

        return $this->encryptSecretsForDatabase($data);
    }

    private function maskSecretsForFrontend(array $data): array {
        unset($data['configSecrets']);

        if (empty($data['config']) || empty($data['configSchema'])) {
            return $data;
        }

        foreach ($data['configSchema'] as $fieldSchema) {
            $fieldName = $fieldSchema['name'] ?? null;

            if (empty($fieldName) || empty($fieldSchema['secret']) || !array_key_exists($fieldName, $data['config'])) {
                continue;
            }

            $data['config'][$fieldName] = empty($data['config'][$fieldName]) ? '' : '********';
        }

        return $data;
    }

    private function encryptSecretsForDatabase(array $data): array {
        $config = $data['config'] ?? null;
        $configSchema = $data['configSchema'] ?? null;

        if (empty($config) || empty($configSchema)) {
            return $data;
        }

        $secrets = [];

        foreach ($configSchema as $fieldSchema) {
            $fieldName = $fieldSchema['name'] ?? null;

            if (empty($fieldName) || empty($fieldSchema['secret'])) {
                continue;
            }

            if (array_key_exists($fieldName, $config) && $config[$fieldName] !== '' && $config[$fieldName] !== null) {
                $secrets[$fieldName] = $config[$fieldName];
                unset($config[$fieldName]);
            }
        }

        $data['config'] = $config;

        if (empty($secrets)) {
            $data['configSecrets'] = null;
            return $data;
        }

        try {
            $data['configSecrets'] = EncryptionService::encrypt(json_encode($secrets));
        } catch (Throwable) {
            $data['configSecrets'] = null;
        }

        return $data;
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'provider' => EnrichmentProviderType::class,
            default => parent::isEnum($field)
        };
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, [
                'dailyResetAt',
                'weeklyResetAt',
                'monthlyResetAt',
                'lastUsedAt'
            ], true) || parent::isDateTime($field);
    }

}
