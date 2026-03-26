<?php

declare(strict_types=1);

namespace IBRExplorer\Validator;

use IBRExplorer\Cache\Entity\EntityMetadata;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Interface\HasParentEntities;

class EntityValidator {

    public const string ENTITY_FIELD_REQUIRED = 'Campo obrigatório.';
    public const string ENTITY_FIELD_INVALID = 'Valor inválido.';

    private const array IGNORE_ENTITY_FIELDS = [
        'id',
        'entityStatus',
        'key',
        'createdAt',
        'createdBy',
        'updatedAt',
        'updatedBy',
    ];

    protected ?Entity $currentEntity;
    protected array $requiredFields = [];
    protected array $messages = [];

    public function isValid(Entity $entity): bool {
        $this->messages = [];

        $metadata = EntityMetadata::of($entity::class);

        foreach ($metadata->fields as $field) {
            $isNullable = $metadata->fieldsMeta[$field]['nullable'] ?? false;
            $ignore = in_array($field, self::IGNORE_ENTITY_FIELDS) || (
                    $isNullable &&
                    !in_array($field, $this->requiredFields)
                );

            $validateChild = !empty($entity->$field) &&
                !empty($metadata->relations[$field]['parents']) &&
                (in_array($entity::class, $metadata->relations[$field]['parents']));

            if ($ignore && !$validateChild) {
                $fieldIsDefined = array_key_exists($field, get_object_vars($entity));
                $isValueNull = $fieldIsDefined && ($entity->$field === null);

                if (!$fieldIsDefined && !$isValueNull && !empty($this->currentEntity->$field)) {
                    $entity->$field = $this->currentEntity->$field;
                }

                continue;
            }

            $requireField = ($metadata->relations[$field]['isMulti'] ?? false)
                ? empty($entity->$field) && empty($this->currentEntity->$field)
                : !isset($entity->$field) && !isset($this->currentEntity->$field);

            if ($requireField) {
                $this->addMessage($field, self::ENTITY_FIELD_REQUIRED);
            } elseif ($validateChild) {
                $messages = $this->validateChildren(
                    $entity->$field,
                    $this->currentEntity->$field ?? null
                );

                if (!empty($messages)) {
                    $this->addMessage($field, $messages);
                }
            } else {
                $entity->$field ??= $this->currentEntity->$field;
            }
        }

        if (!empty($entity->getMessages())) {
            $this->messages = array_merge($this->messages, $entity->getMessages());
        }

        $this->currentEntity = null;

        return empty($this->messages);
    }

    protected function addMessage(string $field, string|array $message, ?string $parentEntity = null): void {
        if (!empty($parentEntity)) {
            $this->messages[$parentEntity][$field] = $message;
        } else {
            $this->messages[$field] = $message;
        }
    }

    /**
     * @param Entity|Entity[] $children
     * @param Entity|array|null $currentChildren
     * @return array|null
     */
    protected function validateChildren(Entity|array $children, Entity|array|null $currentChildren): ?array {
        $messages = [];

        if (is_array($children)) {
            foreach ($children as $key => $child) {
                $childMessages = $this->validateChildren($child, $currentChildren[$key] ?? null);

                if (!empty($childMessages)) {
                    $messages[$key] = $childMessages;
                }
            }

            return $messages;
        }

        $childMetadata = EntityMetadata::of($children::class);

        foreach ($childMetadata->fields as $field) {
            $ignore = in_array($field, self::IGNORE_ENTITY_FIELDS) ||
                $childMetadata->fieldsMeta[$field]['nullable'] ||
                (($children instanceof HasParentEntities) && in_array($field, array_keys($children->getParentEntities())));

            if ($ignore) {
                continue;
            } elseif (!isset($children->$field) && !isset($currentChildren->$field)) {
                $messages[$field] = self::ENTITY_FIELD_REQUIRED;
            } else {
                $children->$field ??= $currentChildren->$field;
            }
        }

        return $messages;
    }

    public function getMessages(): array {
        return $this->messages;
    }

    public function setCurrentEntity(Entity $entity): void {
        $this->currentEntity = $entity;
    }

    protected function addRequiredField(string $fieldName): void {
        $this->requiredFields[] = $fieldName;
    }
}
