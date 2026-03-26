<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Interface\HasParentEntities;

abstract class PcapChild extends Entity implements HasParentEntities {

    public Pcap $pcap;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'pcap' => Pcap::class,
            default => parent::isEntity($field)
        };
    }

    public function getParentEntities(): array {
        return ['pcap' => Pcap::class];
    }

}