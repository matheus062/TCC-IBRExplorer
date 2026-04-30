<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\Pcap;

enum PcapHeaderType: string {

    case Pcap = 'pcap';
    case PcapNg = 'pcapng';
    case Unknown = 'unknown';

}
