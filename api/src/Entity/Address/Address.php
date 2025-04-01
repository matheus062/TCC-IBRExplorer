<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Address;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Util\ZipCode;

class Address extends Entity {

    public State $state;
    public City $city;
    public string $street;
    public string $number;
    public string $neighborhood;
    public ?string $complement;
    public ZipCode $zipCode;


    public function isEntity(string $field): ?string {
        return match ($field) {
            'state' => State::class,
            'city' => City::class,
            default => parent::isEntity($field)
        };
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'zipCode' => ZipCode::class,
            default => parent::isValueObject($field),
        };
    }

}