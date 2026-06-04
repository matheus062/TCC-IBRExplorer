<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use DateTime;
use IBRExplorer\Entity\Enum\Pcap\PcapProtocolType;

class PcapPacket extends PcapChild {

    public DateTime $timestamp;
    public int $packetNumber;
    public int $offset;
    public int $capturedLen;
    public int $originalLen;
    public ?string $flowKey = null;
    public ?string $srcIp;
    public ?string $dstIp;
    public ?int $srcPort = null;
    public ?int $dstPort = null;
    public ?PcapProtocolType $protocol = null;
    public ?int $ipVersion = null;
    public ?int $ttl = null;
    public ?int $ipLength = null;
    public ?int $payloadSize = null;
    public ?int $tcpFlags = null;
    public ?int $icmpType = null;
    public ?int $icmpCode = null;

    protected function isDateTime(string $field): bool {
        return ($field === 'timestamp') || parent::isDateTime($field);
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'protocol' => PcapProtocolType::class,
            default => parent::isEnum($field)
        };
    }

}
