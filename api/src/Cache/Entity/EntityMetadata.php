<?php
declare(strict_types=1);

namespace IBRExplorer\Cache\Entity;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Interface\HasParentEntities;
use IBRExplorer\Entity\Interface\HasSingleRelationship;
use IBRExplorer\Util\File;
use IBRExplorer\Util\Strings;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

class EntityMetadata {

    private const string APC_PREFIX = 'entity_metadata:';
    private const int TTL = 86_400;

    private static array $cache = [];

    public function __construct(
        public readonly string $table,
        public readonly array  $fields,
        public readonly array  $fieldsMeta,
        public readonly array  $scalar,
        public readonly array  $files,
        public readonly array  $relations,
        public readonly array  $dbRows,
    ) {
    }

    public static function of(string $class): self {
        $cached = self::$cache[$class]
            ?? self::loadApcu($class)
            ?? self::buildAndStore($class);

        if (empty(self::$cache[$class])) {
            self::$cache[$class] = $cached;
        }

        return $cached;
    }

    private static function loadApcu(string $class): ?self {
        if (!function_exists('apcu_fetch')) {
            return null;
        }

        $cached = apcu_fetch(self::APC_PREFIX . $class);

        return ($cached instanceof self)
            ? $cached
            : null;
    }

    private static function buildAndStore(string $class): self {
        $meta = self::build($class);

        if (function_exists('apcu_store')) {
            apcu_store(self::APC_PREFIX . $class, $meta, self::TTL);
        }

        return $meta;
    }

    private static function build(string $class): self {
        /** @var Entity $entity */
        $entity = new $class(0);

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new RuntimeException('Não foi possível instânciar o reflection da classe `' . $class . '`');
        }

        $table = Strings::getEntityTableName($class);

        $fields = [];
        $fieldsMeta = [];
        $scalarFields = [];
        $relationFields = [];
        $fileFields = [];
        $selectFields = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $type = $property->getType()?->getName();
            $nullable = $property->getType()?->allowsNull() ?? true;

            $fields[] = $name;
            $fieldsMeta[$name] = [
                'type' => $type,
                'nullable' => $nullable,
            ];

            if (
                !empty($valueObjectClass = $entity->isValueObject($name)) &&
                (is_a($valueObjectClass, File::class, true))
            ) {
                $fileFields[] = $name;
                $selectFields[] = $name;
            } elseif ($childClass = $entity->isEntity($name)) {
                /** @var Entity $childTemplate */
                $childTemplate = new $childClass(0);
                $parents = ($childTemplate instanceof HasParentEntities)
                    ? $childTemplate->getParentEntities()
                    : [];
                $isSingleRelation = !empty($parents) &&
                    ($childTemplate instanceof HasSingleRelationship) &&
                    in_array($entity::class, $parents);
                $isMulti = ($type === 'array');

                $relationFields[$name] = [
                    'class' => $childClass,
                    'table' => Strings::getEntityTableName($childClass),
                    'isMulti' => $isMulti,
                    'parents' => $parents,
                    'isSingleRelation' => $isSingleRelation,
                ];

                if (!$isMulti && !$isSingleRelation) {
                    $selectFields[] = $name;
                }
            } else {
                $scalarFields[] = $name;
                $selectFields[] = $name;
            }
        }

        return new self(
            $table,
            $fields,
            $fieldsMeta,
            $scalarFields,
            $fileFields,
            $relationFields,
            $selectFields,
        );
    }

}