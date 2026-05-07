<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Interface\HasSingleRelationship;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Util\SimpleArray;

class Pcap extends Entity implements HasSingleRelationship {

    public PcapFile $file;
    public DateTime $startTimestamp;
    public DateTime $endTimestamp;
    public ?int $packetsTotal;
    public ?SimpleArray $protocols;
    public ?string $checksum;
    public ?int $capturedBytes;
    public ?int $flowsTotal;
    public ?PcapHeader $header;
    /**
     * @var PcapFlow[]|null
     */
    public ?array $flows;
    /**
     * @var PcapPacket[]
     */
    public array $packets;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'file' => PcapFile::class,
            'header' => PcapHeader::class,
            'flows' => PcapFlow::class,
            'packets' => PcapPacket::class,
            default => parent::isEntity($field)
        };
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'protocols' => SimpleArray::class,
            default => parent::isValueObject($field)
        };
    }

    public function getParentEntities(): array {
        return ['file' => PcapFile::class];
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, ['startTimestamp', 'endTimestamp'], true) || parent::isDateTime($field);
    }

}
