<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\User;

use Exception;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Entity\User\UserRole;
use IBRExplorer\Repository\EntityRepository;
use IBRExplorer\Repository\Exception\ForbiddenEntityException;
use JetBrains\PhpStorm\ArrayShape;

class UserRepository extends EntityRepository {

    public function __construct() {
        parent::__construct(User::class);
    }

    /**
     * @throws Exception
     */
    public function readByEmail(string $email, array $fields = ['*']): User|false {
        /** @var User|false $user */
        $user = $this->list(
            ['id'],
            ['email' => $email],
            limit: 1
        )['entities'][0] ?? false;

        if ($user === false) {
            return false;
        }

        return $this->read($user->id, $fields);
    }

    #[ArrayShape([
        'entities' => 'array',
        'total' => 'int'
    ])] public function list(
        array $fields = ['id', 'key'],
        array $where = [],
        array $orderBy = [],
        int   $limit = 15,
        int   $page = 1,
        array $searchParams = []
    ): array {
        if (empty($where['id'])) {
            $where['id'] = [
                'operator' => '<>',
                'value' => 1,
            ];
        }

        return parent::list($fields, $where, $orderBy, $limit, $page, $searchParams);
    }

    public function read(int $id, array $fields = ['*']): Entity|false {
        if ($id === 1) {
            return false;
        }

        return parent::read($id, $fields);
    }

    protected function prepareEntityDataToSave(
        Entity $entity,
        array  &$filesToSave,
        array  &$childrenToSave,
        bool   $isChild = false
    ): array {
        if ($entity instanceof User) {
            if ($entity->checkUserHasRole(UserRoleType::System)) {
                throw new ForbiddenEntityException('Usuários do tipo System não podem ser salvos.');
            }

            if (!$entity->isNew() && !empty($entity->roles)) {
                $userRoleRepository = IBRExplorerApi::getInstance()->getEntityRepository(UserRole::class);

                foreach ($entity->roles as $role) {
                    if (!$role->isNew() || empty($role->type)) {
                        continue;
                    }

                    $currentRole = $userRoleRepository->list(where: ['user' => $entity->id, 'type' => $role->type])['entities'][0] ?? false;

                    if ($currentRole !== false) {
                        $role->setId($currentRole->id);
                    }
                }
            }

        }

        return parent::prepareEntityDataToSave($entity, $filesToSave, $childrenToSave, $isChild);
    }


}