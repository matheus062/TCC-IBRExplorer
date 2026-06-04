<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enrichment;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentStatus;
use IBRExplorer\Util\JsonField;

class EnrichmentResult extends Entity {

    public EnrichmentTarget $target;
    public EnrichmentIntegration $integration;
    public EnrichmentStatus $status;
    public ?JsonField $data;
    public ?JsonField $summary;
    public ?int $confidence;
    public ?string $error;
    public ?DateTime $fetchedAt;
    public ?DateTime $expiresAt;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'target' => EnrichmentTarget::class,
            'integration' => EnrichmentIntegration::class,
            default => parent::isEntity($field)
        };
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'data', 'summary' => JsonField::class,
            default => parent::isValueObject($field)
        };
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'status' => EnrichmentStatus::class,
            default => parent::isEnum($field)
        };
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, ['fetchedAt', 'expiresAt'], true) || parent::isDateTime($field);
    }

}
