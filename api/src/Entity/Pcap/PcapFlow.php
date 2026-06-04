<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use Brick\Math\BigDecimal;
use DateTime;
use IBRExplorer\Entity\Enum\Pcap\PcapProtocolType;

class PcapFlow extends PcapChild {

    public string $flowKey;
    public string $srcIp;
    public string $dstIp;
    public ?int $srcPort;
    public ?int $dstPort;
    public PcapProtocolType $protocol;
    public ?int $icmpType = null;
    public ?int $icmpCode = null;
    public int $packetCount;
    public BigDecimal $bytesTotal;
    public DateTime $startTimestamp;
    public DateTime $endTimestamp;

    protected function isDateTime(string $field): bool {
        return in_array($field, ['startTimestamp', 'endTimestamp'], true) || parent::isDateTime($field);
    }

    protected function isDecimal(string $field): bool {
        return ($field === 'bytesTotal') || parent::isDecimal($field);
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'protocol' => PcapProtocolType::class,
            default => parent::isEnum($field)
        };
    }

}
