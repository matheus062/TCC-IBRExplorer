<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use IBRExplorer\Entity\Enum\Pcap\PcapHeaderType;
use IBRExplorer\Entity\Interface\HasSingleRelationship;

class PcapHeader extends PcapChild implements HasSingleRelationship {

    public int $magicNumber;
    public int $versionMajor;
    public int $versionMinor;
    public int $linkType;
    public int $snapLen;
    public PcapHeaderType $headerType;

    public function setData(array $data): void {
        parent::setData($data);

        if (empty($this->headerType)) {
            $this->headerType ??= PcapHeaderType::Unknown;
        }
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'headerType' => PcapHeaderType::class,
            default => parent::isEnum($field)
        };
    }

}
