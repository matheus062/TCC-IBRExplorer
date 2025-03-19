<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\User;

enum UserRoleType: int {

    case System = 1;
    case Admin = 2;
    case Management = 3;
    case Support = 4;
    case Logistics = 5;
    case Operational = 6;
    case Inspector = 7;
    case Documentation = 8;
    case Reviewer = 9;
    case Certifier = 10;
    case Finance = 11;
    case Customer = 12;

}