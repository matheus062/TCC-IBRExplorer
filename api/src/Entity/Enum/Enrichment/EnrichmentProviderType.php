<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\Enrichment;

enum EnrichmentProviderType: int {

    case MaxMindGeoLite2 = 1;
    case TeamCymruAsn = 2;
    case ReverseDns = 3;
    case ShodanInternetDb = 4;
    case Censys = 5;
    case AbuseIpDb = 6;

}
