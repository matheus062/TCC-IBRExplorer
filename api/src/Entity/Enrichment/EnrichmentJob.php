<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enrichment;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentStatus;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Util\JsonField;

class EnrichmentJob extends Entity {

    public ?Pcap $pcap;
    public EnrichmentStatus $status;
    public ?JsonField $providers;
    public ?JsonField $targetTypes;
    public ?int $totalTargets;
    public int $processedTargets;
    public int $succeededTargets;
    public int $failedTargets;
    public ?DateTime $startedAt;
    public ?DateTime $finishedAt;
    public ?string $error;

    public function setData(array $data): void {
        parent::setData($data);

        if ($this->isNew()) {
            $this->status ??= EnrichmentStatus::Pending;
            $this->processedTargets ??= 0;
            $this->succeededTargets ??= 0;
            $this->failedTargets ??= 0;
        }
    }

    public function isEntity(string $field): ?string {
        return match ($field) {
            'pcap' => Pcap::class,
            default => parent::isEntity($field)
        };
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'providers', 'targetTypes' => JsonField::class,
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
        return in_array($field, ['startedAt', 'finishedAt'], true) || parent::isDateTime($field);
    }

}
