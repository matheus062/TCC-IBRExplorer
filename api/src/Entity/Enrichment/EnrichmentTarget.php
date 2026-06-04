<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enrichment;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentTargetType;

class EnrichmentTarget extends Entity {

    public EnrichmentTargetType $type;
    public string $value;
    public string $normalizedValue;
    public ?DateTime $firstSeenAt;
    public ?DateTime $lastSeenAt;
    public ?DateTime $lastEnrichedAt;

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'type' => EnrichmentTargetType::class,
            default => parent::isEnum($field)
        };
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, [
                'firstSeenAt',
                'lastSeenAt',
                'lastEnrichedAt'
            ], true) || parent::isDateTime($field);
    }

}
