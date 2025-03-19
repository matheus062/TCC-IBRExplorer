<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Address;

use IBRExplorer\Entity\Address\Country;
use IBRExplorer\Service\EntityService;
use IBRExplorer\Service\Interface\HasSearchParams;

class CountryService extends EntityService implements HasSearchParams {

    public function __construct() {
        parent::__construct(Country::class);
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