<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Address;

use IBRExplorer\Entity\Entity;

class City extends Entity {

    public State $state;
    public string $name;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'state' => State::class,
            default => parent::isEntity($field)
        };
    }

}