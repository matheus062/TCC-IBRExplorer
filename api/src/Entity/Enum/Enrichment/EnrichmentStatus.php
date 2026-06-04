<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\Enrichment;

enum EnrichmentStatus: int {

    case Pending = 1;
    case Running = 2;
    case Success = 3;
    case Error = 4;
    case Skipped = 5;

}
