<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\User;

enum UserRoleType: int {

    case System = 1;
    case Admin = 2;
    case Support = 3;
    case User = 4;

}