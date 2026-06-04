<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\Enrichment;

enum EnrichmentTargetType: int {

    case Ip = 1;
    case Domain = 2;
    case Url = 3;
    case Hash = 4;
    case Asn = 5;

}
