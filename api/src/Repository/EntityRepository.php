<?php

declare(strict_types=1);

namespace IBRExplorer\Repository;

use DateTime;
use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Cache\Entity\EntityCacheController;
use IBRExplorer\Cache\Entity\EntityMetadata;
use IBRExplorer\Database\MySql;
use IBRExplorer\Entity\Address\Address;
use IBRExplorer\Entity\Address\State;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\Interface\HasParentEntities;
use IBRExplorer\Entity\Interface\HasSingleRelationship;
use IBRExplorer\Repository\Exception\DuplicateEntityException;
use IBRExplorer\Repository\Exception\InvalidDeleteException;
use IBRExplorer\Repository\Exception\InvalidFileDataException;
use IBRExplorer\Storage\FileSystem;
use IBRExplorer\Util\File;
use IBRExplorer\Util\Strings;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;

class EntityRepository {

    public readonly string $entityClass;
    public readonly string $table;

    protected MySql $db;
    protected EntityCacheController $cache;

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
        $this->cache = new EntityCacheController();
    }

    /**
     * @throws Exception
     */
    public function read(
        int   $id,
        array $fields = ['*'],
        bool  $getFileData = false
    ): Entity|false {
        $dbFields = $this->prepareFields($fields);
        $row = $this->db->rowById($this->table, $id, $dbFields);

        if ($row === false) {
            return false;
        }

        $entity = new $this->entityClass($row);

        return $this->convertEntity($entity, $fields, true, $getFileData);
    }

    protected final function prepareFields(array $fields): array {
        $meta = EntityMetadata::of($this->entityClass);

        if ($fields !== ['*']) {
            $dbFields = [];

            foreach ($fields as $key => $value) {
                $field = is_string($key) ? $key : $value;

                if (!in_array($field, $meta->dbRows) || in_array($field, $dbFields)) {
                    continue;
                }

                $dbFields[] = $field;
            }

            if (!in_array('name', $fields) && in_array('name', $meta->scalar, true)) {
                $dbFields[] = 'name';
            }

            $required = ['id', 'key', 'entityStatus'];
            $dbFields = array_values(array_unique(array_merge(
                $dbFields,
                $required
            )));
        } else {
            $dbFields = $meta->dbRows;
        }

        return $dbFields;
    }

    /**
     * @throws Exception
     */
    protected function convertEntity(
        Entity $entity,
        array  &$fields,
        bool   $getChildFields = true,
        bool   $getFileData = false,
    ): Entity {
        $entityMeta = EntityMetadata::of($entity::class);

        if (empty($fields)) {
            $fields = ['entity' => ['id', 'key', 'entityStatus']];
        } elseif (($fields[0] ?? '') === '*') {
            $fields = [
                ...$entityMeta->fields,
                ...$fields
            ];
        }

        if (!isset($fields['entity'])) {
            foreach ($fields as $index => $childField) {
                $fields['entity'][] = is_numeric($index) ? $childField : $index;

                if (is_numeric($index)) {
                    unset($fields[$index]);
                }
            }
        }

        foreach ($fields['entity'] as $childKey => $field) {
            $childRelation = $entityMeta->relations[$field] ?? false;

            if (!empty($childRelation)) {
                if (!$getChildFields || empty($fields[$field]) || in_array($field, ['createdBy', 'updatedBy'])) {
                    $fields[$field] = ['id', 'key', 'entityStatus'];
                }

                $childClass = $childRelation['class'];
                $childRepository = IBRExplorerApi::getInstance()->getEntityRepository($childClass);

                switch ($childClass) {
                    case Address::class:
                        $fields[$field] = array_merge($fields[$field], [
                            'state', 'city', 'street', 'number', 'neighborhood', 'complement', 'zipCode'
                        ]);

                        break;
                    case State::class:
                        $fields[$field][] = 'abbreviation';

                        break;
                    default:
                        break;
                }

                if (!$childRelation['isMulti']) {
                    if ($childRelation['isSingleRelation']) {
                        $id = $entity->id;
                    } elseif (!empty($entity->$field)) {
                        $id = $entity->$field->id;
                    } else {
                        continue;
                    }

                    $childFields = $fields[$field]['entity'] ?? $fields[$field];
                    $cacheKey = $childClass . ':' . $id;

                    if ($cached = $this->cache->retrieveEntity($cacheKey, $childFields)) {
                        $entity->$field = $cached;

                        continue;
                    }

                    $child = $childRepository->read($id, $childFields);

                    if (($child === false) || ($child->entityStatus !== EntityStatus::Active)) {
                        continue;
                    }

                    $this->cache->storeEntity($cacheKey, $child, $childFields);
                    $entity->$field = $child;

                    continue;
                }

                $entity->$field = [];
                $parentField = array_search($entity::class, $childRelation['parents'], true);

                if (empty($parentField)) {
                    continue;
                }

                $childFields = $fields[$field]['entity'] ?? $fields[$field];
                $children = $childRepository->list(
                    $childFields,
                    [
                        'entityStatus' => EntityStatus::Active,
                        $parentField => $entity->id
                    ],
                    orderBy: ['id'],
                    limit: 0
                );

                if (empty($children['entities'])) {
                    continue;
                }

                foreach ($children['entities'] as $child) {
                    $entity->{$field}[] = $child;
                }
            } elseif (
                $getFileData &&
                in_array($field, $entityMeta->files, true) &&
                !empty($entity->$field)
            ) {
                FileSystem::getInstance()->readFile($entity->{$field . 'EntityPath'}(), $entity->$field);
            } elseif (!in_array($field, $entityMeta->scalar, true)) {
                unset($fields['entity'][$childKey]);
            }
        }

        $fields['entity'] = array_values($fields['entity']);

        return $entity;
    }

    /**
     * @throws Exception
     */
    #[ArrayShape([
        'entities' => 'array',
        'total' => 'int'
    ])]
    public function list(
        array $fields = ['id', 'key', 'entityStatus'],
        array $where = [],
        array $orderBy = [],
        int   $limit = 15,
        int   $page = 1,
        array $searchParams = [],
    ): array {
        $list = $this->db->rows(
            $this->table,
            $this->prepareFields($fields),
            $this->prepareWhere($where),
            [],
            $this->prepareOrderBy($orderBy),
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

    protected final function prepareWhere(array $filters): array {
        if (empty($filters)) {
            return [];
        }

        $meta = EntityMetadata::of($this->entityClass);
        $where = [];

        foreach ($filters as $key => $value) {
            if (str_contains($key, '.')) {
                [$prefix, $column] = explode('.', $key, 2);

                if (isset($meta->relations[$prefix])) {
                    $table = $meta->relations[$prefix]['table'];
                    $where[$table . '.' . $column] = $value;
                }
            } elseif (
                in_array($key, $meta->scalar, true) ||
                in_array($key, $meta->files, true) ||
                isset($meta->relations[$key])
            ) {
                $where[$key] = $value;
            }
        }

        return $where;
    }

    protected final function prepareOrderBy(array $orderBy): array {
        if (empty($orderBy)) {
            return [];
        }

        $meta = EntityMetadata::of($this->entityClass);
        $clean = [];

        foreach ($orderBy as $expression) {
            if (preg_match('/\s+(ASC|DESC)$/i', $expression, $dir)) {
                $direction = ' ' . strtoupper($dir[1]);
                $fieldPart = substr($expression, 0, -strlen($dir[0]));
            } else {
                $direction = '';
                $fieldPart = $expression;
            }

            if (str_contains($fieldPart, '.')) {
                [$prefix, $column] = explode('.', $fieldPart, 2);

                if (isset($meta->relations[$prefix])) {
                    $table = $meta->relations[$prefix]['table'];
                    $clean[] = $table . '.' . $column . $direction;
                }
            } elseif (
                in_array($fieldPart, $meta->scalar, true) ||
                in_array($fieldPart, $meta->files, true) ||
                isset($meta->relations[$fieldPart])
            ) {
                $clean[] = $fieldPart . $direction;
            }
        }

        return $clean;
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

                if (!empty($valueObject) && is_array($value)) {
                    $data[$field] = json_encode($value);
                }

                if (is_a($valueObject, File::class, true)) {
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
                // TODO: Fazer a validação da existência das subEntidades no banco de dados (quando a entidade nao é filha)
                $data[$field] = $value['id'] ?? null;

                continue;
            } elseif (!is_null($entity->$field)) {
                $childrenToSave[$childClass] = $entity->$field;
            }

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
    public function changeEntityStatus(Entity $entity, EntityStatus $status): bool {
        return $this->db->updateRow($this->table, ['entityStatus' => $status], $entity->id);
    }

    /**
     * @throws Exception
     */
    public function delete(Entity $entity): void {
        if ($entity->isNew()) {
            throw new InvalidDeleteException();
        }

        $success = $this->db->deleteRow($this->table, $entity->id);

        if (!$success) {
            throw new InvalidDeleteException(
                'Ocorreu um erro desconhecido ao deletar a entidade. ' . PHP_EOL .
                'table: ' . $this->table . PHP_EOL .
                'id: ' . $entity->id
            );
        }
    }

}