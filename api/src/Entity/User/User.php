<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\User;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use IBRExplorer\Util\Email;
use IBRExplorer\Util\File;

class User extends Entity {

    public string $name;
    public Email $email;
    public ?File $profileImage;
    /**
     * @var UserRole[]
     */
    public array $roles;

    public function isEntity(string $field): ?string {
        return match ($field) {
            'roles' => UserRole::class,
            default => parent::isEntity($field)
        };
    }

    public function checkUserHasRole(UserRoleType $type): bool {
        if (empty($this->roles)) {
            return false;
        }

        foreach ($this->roles as $role) {
            if ($role->type === $type) {
                return true;
            }
        }

        return false;
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'email' => Email::class,
            'profileImage' => File::class,
            default => parent::isValueObject($field)
        };
    }

    /**
     * @return string
     * @noinspection PhpUnused
     */
    public function profileImageEntityPath(): string {
        return 'user/' . $this->id . '/profile';
    }

}
