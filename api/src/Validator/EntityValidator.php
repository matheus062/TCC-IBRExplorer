<?php

declare(strict_types=1);

namespace IBRExplorer\Validator;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Interface\HasParentEntities;
use ReflectionClass;
use ReflectionProperty;

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

        $reflection = new ReflectionClass($entity);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $ignore = in_array($propertyName, self::IGNORE_ENTITY_FIELDS) || (
                    $property->getType()->allowsNull() &&
                    !in_array($propertyName, $this->requiredFields)
                );

            $validateChild =
                isset($entity->$propertyName) &&
                !empty($entity->isEntity($propertyName)) &&
                ($entity->$propertyName instanceof HasParentEntities) &&
                (in_array($entity::class, $entity->$propertyName->getParentEntities()));

            if ($ignore && !$validateChild) {
                continue;
            } elseif (!isset($entity->$propertyName) && !isset($this->currentEntity->$propertyName)) {
                $this->addMessage($propertyName, self::ENTITY_FIELD_REQUIRED);
            } elseif ($validateChild) {
                $messages = $this->validateChildren($entity->$propertyName);

                if (!empty($messages)) {
                    $this->addMessage($propertyName, $messages);
                }
            } else {
                $entity->$propertyName ??= $this->currentEntity->$propertyName;
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
     * @return array|null
     */
    protected function validateChildren(Entity|array $children): ?array {
        $messages = [];

        if (is_array($children)) {
            foreach ($children as $key => $child) {
                $childMessages = $this->validateChildren($child);

                if (!empty($childMessages)) {
                    $messages[$key] = $childMessages;
                }
            }

            return $messages;
        }

        $reflection = new ReflectionClass($children);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $ignore = in_array($propertyName, self::IGNORE_ENTITY_FIELDS) ||
                $property->getType()->allowsNull() ||
                (($children instanceof HasParentEntities) && (in_array($propertyName, array_keys($children->getParentEntities()))));

            if ($ignore) {
                continue;
            } elseif (!isset($children->$propertyName)) {
                $messages[$propertyName] = self::ENTITY_FIELD_REQUIRED;
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
