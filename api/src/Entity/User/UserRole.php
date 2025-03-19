<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\User;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use IBRExplorer\Entity\Interface\HasParentEntities;

class UserRole extends Entity implements HasParentEntities {

    public User $user;
    public UserRoleType $type;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'user' => User::class,
            default => parent::isEntity($field)
        };
    }

    public function getParentEntities(): array {
        return ['user' => User::class];
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'type' => UserRoleType::class,
            default => parent::isEnum($field)
        };
    }
}