<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Address;

use Exception;
use IBRExplorer\Entity\Address\Address;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Repository\EntityRepository;

class AddressRepository extends EntityRepository {

    public function __construct() {
        parent::__construct(Address::class);
    }

    /**
     * @param Address $entity
     * @return bool
     * @throws Exception
     */
    public function save(Entity $entity): bool {
        if (
            !empty($entity->state->id) &&
            !empty($entity->city->id) &&
            !empty($entity->street) &&
            !empty($entity->number) &&
            !empty($entity->neighborhood) &&
            !empty($entity->zipCode)
        ) {
            /** @var Address|false $address */
            $address = $this->list(
                ['id', 'entityStatus'],
                [
                    'state' => $entity->state->id,
                    'city' => $entity->city->id,
                    'street' => $entity->street,
                    'number' => $entity->number,
                    'neighborhood' => $entity->neighborhood,
                    'complement' => $entity->complement ?? null,
                    'zipCode' => $entity->zipCode,
                ],
                limit: 1
            )['entities'][0] ?? false;

            if ($address !== false) {
                $entity->setId($address->id);

                if ($address->entityStatus === EntityStatus::Active) {
                    return true;
                }
            }
        }

        return parent::save($entity);
    }
}