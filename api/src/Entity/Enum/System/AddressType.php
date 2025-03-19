<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\System;

enum AddressType: int {

    case Mailing = 1;
    case Billing = 2;
    case Commercial = 3;

}