<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

class PcapHeader extends PcapChild {

    public int $magicNumber;
    public int $versionMajor;
    public int $versionMinor;
    public int $linkType;
    public int $snapLen;

    // Implementar o isEntity...

}