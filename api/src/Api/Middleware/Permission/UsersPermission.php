<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Middleware\Permission;

use IBRExplorer\Entity\Enum\User\UserRoleType;

class UsersPermission extends Permission {

    protected function getAllowedTypes(): array {
        return [UserRoleType::Admin];
    }
}