<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\PcapFile;

enum PcapFileVisibility: int {

    case Private = 1;
    case Public = 2;

}
