<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Address;

use IBRExplorer\Entity\Entity;

class Country extends Entity {

    public string $name;
    public string $abbreviation;

}