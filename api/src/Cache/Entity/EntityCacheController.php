<?php

declare(strict_types=1);

namespace IBRExplorer\Cache\Entity;

use IBRExplorer\Entity\Entity;

class EntityCacheController {

    private array $cache = [];

    public function storeEntity(string $key, Entity $entity, array $fields): void {
        $hash = $this->generateHash($fields);

        $this->cache[$key] = [
            'entity' => $entity,
            'hash' => $hash,
        ];
    }

    private function generateHash(array $fields): string {
        return md5(json_encode($fields));
    }

    public function retrieveEntity(string $key, array $fields = [], bool $checkHash = true): Entity|false {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $cached = $this->cache[$key];

        if ($checkHash && ($cached['hash'] !== $this->generateHash($fields))) {
            return false;
        }

        return $cached['entity'];
    }
}