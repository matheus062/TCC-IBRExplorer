<?php

declare(strict_types=1);

namespace IBRExplorer\Repository;

use DateTime;
use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Database\MySql;
use IBRExplorer\Entity\Address\Address;
use IBRExplorer\Entity\Address\City;
use IBRExplorer\Entity\Address\Country;
use IBRExplorer\Entity\Address\State;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\Interface\HasParentEntities;
use IBRExplorer\Entity\Interface\HasSingleRelationship;
use IBRExplorer\Repository\Exception\DuplicateEntityException;
use IBRExplorer\Repository\Exception\InvalidFileDataException;
use IBRExplorer\Storage\FileSystem\FileSystem;
use IBRExplorer\Util\File;
use IBRExplorer\Util\Strings;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

class EntityRepository {

    public readonly string $entityClass;
    public readonly string $table;

    protected MySql $db;

    private bool $transactionStarted = false;

    public function __construct(string $entityClass) {
        if (!class_exists($entityClass) || !is_subclass_of($entityClass, Entity::class)) {
            throw new RuntimeException(
                'Entidade não localizada: ' . $entityClass, StatusCode::InternalServerError->value
            );
        }

        $this->entityClass = $entityClass;
        $this->table = Strings::getEntityTableName($entityClass);
        $this->db = MySql::$instance;
    }

    /**
     * @throws Exception
     */
    public function read(int $id, array $fields = ['*']): Entity|false {
        $row = $this->db->rowById($this->table, $id, $fields);

        if ($row === false) {
            return false;
        }

        $entity = new $this->entityClass($row);

        return $this->convertEntity($entity, $fields, true, true);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @noinspection PhpMissingBreakStatementInspection
     */
    protected function convertEntity(
        Entity|string $entity,
        array         &$fields,
        bool          $getChildFields = true,
        bool          $getFileData = false,
    ): Entity {
        if (empty($fields)) {
            $fields = ['entity' => ['id', 'key', 'entityStatus']];
        }

        if (is_string($entity)) {
            /** @var Entity $entity */
            $entity = new $entity(0);
        }

        $reflection = new ReflectionClass($entity);

        if (($fields[0] ?? '') === '*') {
            $fields = [
                'entity' => array_map(
                    fn(ReflectionProperty $property) => $property->getName(),
                    $reflection->getProperties(ReflectionProperty::IS_PUBLIC)
                )
            ];
        } elseif (!isset($fields['entity'])) {
            foreach ($fields as $index => $childField) {
                $fields['entity'][] = is_numeric($index) ? $childField : $index;

                if (is_numeric($index)) {
                    unset($fields[$index]);
                }
            }
        }

        /** @var int $key */
        foreach ($fields['entity'] as $key => $childField) {
            if (!empty($childClass = $entity->isEntity($childField))) {
                $multipleEntities = ($reflection->getProperty($childField)->getType()->getName() === 'array');

                if ($getChildFields) {
                    $fields[$childField] ??= ['*'];
                } else {
                    $fields[$childField] = ['id', 'key', 'entityStatus'];

                    switch ($childClass) {
                        case Address::class:
                            $fields[$childField][] = 'state';
                            $fields[$childField][] = 'city';
                            $fields[$childField][] = 'street';
                            $fields[$childField][] = 'number';
                            $fields[$childField][] = 'neighborhood';
                            $fields[$childField][] = 'complement';
                            $fields[$childField][] = 'zipCode';
                            break;
                        case State::class:
                            $fields[$childField][] = 'abbreviation';
                        case Country::class:
                        case City::class:
                            $fields[$childField][] = 'name';
                        default:
                            break;
                    }
                }

                $childTemplate = new $childClass(0);

                if (!$multipleEntities) {
                    if (
                        ($childTemplate instanceof HasSingleRelationship) &&
                        in_array($entity::class, $childTemplate->getParentEntities())
                    ) {
                        $id = $entity->id;
                    } elseif (!empty($entity->$childField)) {
                        $id = $entity->$childField->id;
                    } else {
                        continue;
                    }

                    $childRow = $this->db->rowById(
                        Strings::getEntityTableName($childClass),
                        $id,
                        $fields[$childField]['entity'] ?? $fields[$childField]
                    );

                    if ($childRow === false) {
                        continue;
                    }

                    $child = new $childClass($childRow);

                    if ($child->entityStatus !== EntityStatus::Active) {
                        unset($fields[$childField]);
                        unset($entity->$childField);

                        continue;
                    }

                    $entity->$childField = $child;
                    self::convertEntity($entity->$childField, $fields[$childField], false);

                    continue;
                }

                $entity->$childField = [];
                $parentField = array_search($entity::class, $childTemplate->getParentEntities());

                if (!($childTemplate instanceof HasParentEntities) || ($parentField === false)) {
                    continue;
                }

                $childRows = $this->db->rows(
                    Strings::getEntityTableName($childClass),
                    $fields[$childField]['entity'] ?? $fields[$childField],
                    [
                        'entityStatus' => EntityStatus::Active,
                        $parentField => $entity->id
                    ],
                    orderBy: ['id'],
                    limit: 0,
                );

                if (empty($childRows['entities'])) {
                    continue;
                }

                foreach ($childRows['entities'] as $childRow) {
                    $childEntity = new $childClass($childRow);
                    self::convertEntity($childEntity, $fields[$childField], false);
                    $entity->$childField[] = $childEntity;
                }
            } elseif (
                $getFileData &&
                !empty($valueObjectClass = $entity->isValueObject($childField)) &&
                ($valueObjectClass === File::class) &&
                !empty($entity->$childField)
            ) {
                FileSystem::getInstance()->readFile($entity->{$childField . 'EntityPath'}(), $entity->$childField);
            } elseif (!$reflection->hasProperty($childField)) {
                unset($fields['entity'][$key]);
            }
        }

        $fields['entity'] = array_values($fields['entity']);

        return $entity;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[ArrayShape([
        'entities' => 'array',
        'total' => 'int'
    ])]
    public function list(
        array $fields = ['id', 'key'],
        array $where = [],
        array $orderBy = [],
        int   $limit = 15,
        int   $page = 1,
        array $searchParams = [],
    ): array {
        $list = $this->db->rows(
            $this->table,
            $fields,
            $where,
            [],
            $orderBy,
            $limit,
            $page,
            $searchParams
        );
        $entities = [];

        if (!empty($list['entities'])) {
            foreach ($list['entities'] as $row) {
                $entity = new $this->entityClass($row);
                $this->convertEntity($entity, $fields);
                $entities[] = $entity;
            }
        }

        return [
            'entities' => $entities,
            'total' => $list['total'],
        ];
    }

    /**
     * @throws Exception
     */
    public function save(Entity $entity): bool {
        try {
            $this->beginTransaction();

            $childrenToSave = [];
            $filesToSave = [];

            if ($entity->isNew()) {
                $this->createEntity($entity, $filesToSave, $childrenToSave);
            } else {
                $this->updateEntity($entity, $filesToSave, $childrenToSave);

                if (!empty($filesToSave)) {
                    foreach ($filesToSave as $field => $file) {
                        if (empty($file->data)) {
                            unset($filesToSave[$field]);
                        }
                    }
                }
            }

            if (!empty($filesToSave)) {
                $this->saveFiles($entity, $filesToSave);
            }

            if (!empty($childrenToSave)) {
                $this->saveChildren($entity, $childrenToSave);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();

            if ($e->getCode() === 1062) {
                if (preg_match("/for key '(.+)'/", $e->getMessage(), $matches) !== false) {
                    $fieldName = $matches[1];

                    if (str_contains($fieldName, '.')) {
                        $fieldName = explode('.', $fieldName)[1];
                    }
                }

                $e = new DuplicateEntityException(
                    'Campo duplicado ao salvar registro.',
                    field: ($fieldName ?? '')
                );
            }

            throw $e;
        }

        return true;
    }

    protected function beginTransaction(): void {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $this->transactionStarted = true;
        }
    }

    /**
     * @throws Exception
     */
    private function createEntity(Entity $entity, array &$filesToSave, array &$childrenToSave): void {
        $data = $this->prepareEntityDataToSave($entity, $filesToSave, $childrenToSave);
        $success = $this->db->insertRow($this->table, $data);
        $entity->setId($this->db->getLastInsertId());

        if (!$success) {
            throw new Exception('Ocorreu um erro desconhecido ao criar a entidade. Tabela: ' . $this->table);
        }
    }

    /**
     * @throws Exception
     */
    protected function prepareEntityDataToSave(
        Entity $entity,
        array  &$filesToSave,
        array  &$childrenToSave,
        bool   $isChild = false
    ): array {
        // TODO: Fazer a validação da existência das subEntidades no banco de dados (quando a entidade nao é filha)

        if ($entity->isNew()) {
            $entity->key ??= Entity::generateKey();
            $entity->createdAt = new DateTime();
            $entity->createdBy = $this->db->getUser();
        } else {
            unset($entity->key);
            unset($entity->createdAt);
            unset($entity->createdBy);
        }

        $entity->updatedAt = new DateTime();
        $entity->updatedBy = $this->db->getUser();

        $data = $entity->jsonSerialize(true);
        unset($data['id']);

        if (!$isChild) {
            unset($data['entityStatus']);
        }

        foreach ($data as $field => $value) {
            $childClass = $entity->isEntity($field);

            if (empty($childClass)) {
                $valueObject = $entity->isValueObject($field);

                if ($valueObject === File::class) {
                    $data[$field] = json_encode($value);
                    $filesToSave[$field] = $entity->$field;
                }

                continue;
            }

            $childTemplate = new $childClass(0);

            if ($childTemplate instanceof Address) {
                IBRExplorerApi::getInstance()->getEntityRepository(Address::class)->save($entity->$field);
                $data[$field] = $entity->$field->id;

                continue;
            } elseif (!($childTemplate instanceof HasParentEntities) || !in_array($entity::class, $childTemplate->getParentEntities())) {
                $data[$field] = $value['id'] ?? null;

                continue;
            }

            $childrenToSave[$childClass] = $entity->$field;
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    private function updateEntity(Entity $entity, array &$filesToSave, array &$childrenToSave): void {
        $data = $this->prepareEntityDataToSave($entity, $filesToSave, $childrenToSave);
        $success = $this->db->updateRow($this->table, $data, $entity->id);

        if (!$success) {
            throw new Exception('Ocorreu um erro desconhecido ao atualizar a entidade. Tabela: ' . $this->table);
        }
    }

    /**
     * @param Entity $entity
     * @param File[] $filesToSave
     * @return void
     * @throws Exception
     */
    private function saveFiles(Entity $entity, array $filesToSave): void {
        foreach ($filesToSave as $field => $file) {
            if (empty($file->data)) {
                throw new InvalidFileDataException();
            }

            $saved = FileSystem::getInstance()->saveFile($entity->{$field . 'EntityPath'}(), $file);

            if (!$saved) {
                throw new InvalidFileDataException();
            }
        }
    }

    /**
     * @throws Exception
     */
    private function saveChildren(Entity $entity, array $children): void {
        if ($entity instanceof HasParentEntities) {
            $grandParent = $entity->getParentEntities();
        }

        foreach ($children as $childClass => $childField) {
            $childrenToSave = is_array($childField) ? $childField : [$childField];
            $childTable = Strings::getEntityTableName($childClass);

            /** @var Entity&HasParentEntities[] $childrenToSave */
            foreach ($childrenToSave as $child) {
                $filesToSave = [];
                $grandChildren = [];
                $data = $this->prepareEntityDataToSave($child, $filesToSave, $grandChildren, true);
                $parentEntities = $child->getParentEntities();

                if ($child->isNew()) {
                    $parentField = array_search($entity::class, $parentEntities);
                    $data[$parentField] = $entity->id;
                    $child->$parentField = $entity;

                    if ($child instanceof HasSingleRelationship) {
                        $data['id'] = $entity->id;
                    }

                    if (!empty($grandParent)) {
                        foreach ($parentEntities as $field => $class) {
                            if (in_array($class, $grandParent)) {
                                $parentField = array_search($class, $grandParent);
                                $data[$field] = $entity->$parentField->id ?? null;
                            }
                        }
                    }

                    $this->db->insertRow($childTable, $data);
                    $child->setId($this->db->getLastInsertId());
                } else {
                    foreach ($parentEntities as $field => $class) {
                        unset($data[$field]);
                    }

                    $this->db->updateRow($childTable, $data, $child->id);

                    if (!empty($filesToSave)) {
                        foreach ($filesToSave as $field => $file) {
                            if (empty($file->data)) {
                                unset($filesToSave[$field]);
                            }
                        }
                    }
                }

                if (!empty($filesToSave)) {
                    $this->saveFiles($child, $filesToSave);
                }

                if (!empty($grandChildren)) {
                    $this->saveChildren($child, $grandChildren);
                }
            }
        }
    }

    protected function commit(): void {
        if ($this->transactionStarted) {
            $this->db->commit();
            $this->transactionStarted = false;
        }
    }

    protected function rollback(): void {
        if ($this->transactionStarted && $this->db->inTransaction()) {
            $this->db->rollback();
            $this->transactionStarted = false;
        }
    }

    /**
     * @throws Exception
     */
    public function changeStatus(Entity $entity, EntityStatus $status): bool {
        return $this->db->updateRow($this->table, ['status' => $status], $entity->id);
    }

}