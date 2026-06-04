<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Middleware\Permission;

use IBRExplorer\Entity\Enum\User\UserRoleType;

class EnrichmentPermission extends Permission {

    protected function postAllowedTypes(): array {
        return $this->getAllowedTypes();
    }

    protected function getAllowedTypes(): array {
        return [
            UserRoleType::Admin,
            UserRoleType::Support,
            UserRoleType::User
        ];
    }

    protected function putAllowedTypes(): array {
        return $this->getAllowedTypes();
    }

    protected function deleteAllowedTypes(): array {
        return $this->getAllowedTypes();
    }
}
