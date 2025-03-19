<?php

namespace IBRExplorer\Entity\Enum\System;

enum EntityStatus: int {

    case Active = 1;
    case Inactive = 2;
    case Deleted = 3;

}
