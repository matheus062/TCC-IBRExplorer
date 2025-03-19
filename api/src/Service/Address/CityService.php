<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Address;

use IBRExplorer\Entity\Address\City;
use IBRExplorer\Service\EntityService;
use IBRExplorer\Service\Interface\HasSearchParams;

class CityService extends EntityService implements HasSearchParams {

    public function __construct() {
        parent::__construct(City::class);
    }

    public function getSearchParams(string $search): array {
        return [
            'name' => [
                $search . '%',
                '%' . $search . '%'
            ]
        ];
    }
}