<?php

declare(strict_types=1);

namespace IBRExplorer\Service;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Cache\Entity\EntityMetadata;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Repository\EntityRepository;
use IBRExplorer\Repository\Exception\DuplicateEntityException;
use IBRExplorer\Service\Interface\HasProcessAfterSave;
use IBRExplorer\Service\Interface\HasProcessBeforeSave;
use IBRExplorer\Service\Interface\HasSearchParams;
use IBRExplorer\Validator\EntityValidator;

class EntityService {

    protected EntityValidator $validator;
    protected EntityRepository $repository;

    private string|array $error;
    private StatusCode $code;

    public function __construct(string $entityClass, EntityValidator $validator = null) {
        $this->validator = $validator ?? new EntityValidator();
        $this->repository = IBRExplorerApi::getInstance()->getEntityRepository($entityClass);
    }

    public function create(Entity|array $data): int|false {
        $this->setError([]);

        if (is_array($data)) {
            unset($data['id']);
            /** @var Entity $entity */
            $entity = new $this->repository->entityClass($data);
            unset($data);
        } else {
            $entity = clone $data;
            unset($data);
            $entity->setId(0);
        }

        if (!$this->validateEntity($entity)) {
            return false;
        }

        return $this->saveEntity($entity)
            ? $entity->id
            : false;
    }

    protected final function validateEntity(Entity $entity): bool {
        if (!$entity->isNew()) {
            $meta = EntityMetadata::of($this->repository->entityClass);
            $fields = array_merge($meta->scalar, $meta->files);

            foreach ($meta->relations as $relName => $relInfo) {
                if (in_array($relName, ['createdBy', 'updatedBy'])) {
                    continue;
                }

                $childMeta = EntityMetadata::of($relInfo['class']);
                $fields[$relName] = $childMeta->fields;
            }

            $currentEntity = $this->getById($entity->id, $fields);

            if ($currentEntity === false) {
                return false;
            }

            $this->validator->setCurrentEntity($currentEntity);
        }

        if (!$this->validator->isValid($entity)) {
            return $this->setError($this->validator->getMessages(), StatusCode::InvalidEntity);
        }

        return true;
    }

    public function getById(int $id, array $fields = ['*'], bool $getFileData = false): Entity|false {
        $this->setError([]);

        try {
            $meta = EntityMetadata::of($this->repository->entityClass);

            if (($fields[0] ?? '') !== '*') {
                $isIncluded = array_filter(
                    array_merge(
                        array_keys($fields),
                        array_values($fields)
                    ),
                    fn($value) => is_string($value)
                );

                $fields = [
                    ...$fields,
                    ...$isIncluded,
                    ...array_values(array_diff($meta->fields, $isIncluded))
                ];
            }
            $entity = $this->repository->read($id, $fields, $getFileData);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        if (($entity === false) || ($entity->entityStatus === EntityStatus::Deleted)) {
            return $this->setError('Registro não localizado.', StatusCode::NotFound);
        }

        return $entity;
    }

    public function getCode(): StatusCode {
        return $this->code ?? StatusCode::Ok;
    }

    protected final function saveEntity(Entity $entity): bool {
        try {
            if ($this instanceof HasProcessBeforeSave) {
                $this->processBeforeSave($entity);
            }

            $saved = $this->repository->save($entity);

            if ($saved && ($this instanceof HasProcessAfterSave)) {
                $this->processAfterSave($entity);
            }
        } catch (DuplicateEntityException $e) {
            return $this->setError([
                'error' => $e->getMessage(),
                'field' => $e->field
            ], $e->getCode());
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        return $saved;
    }

    public function update(int $id, Entity|array $data): bool {
        $this->setError([]);

        if (is_array($data)) {
            $data['id'] = $id;
            /** @var Entity $entity */
            $entity = new $this->repository->entityClass($data);
            unset($data);
        } else {
            $entity = clone $data;
            unset($data);
            $entity->setId($id);
        }

        if (!$this->validateEntity($entity)) {
            return false;
        }

        return $this->saveEntity($entity);
    }

    public function getByKey(string $key, array $fields = ['*']): Entity|false {
        $id = $this->list(['id'], ['key' => $key], limit: 1)['entities'][0]->id ?? false;

        if ($id === false) {
            return $this->setError('Registro não localizado.', StatusCode::NotFound);
        }

        return $this->getById($id, $fields);
    }

    public function list(
        array  $fields = ['id', 'key'],
        array  $where = [],
        array  $orderBy = ['id DESC'],
        int    $limit = 15,
        int    $page = 1,
        string $search = '',
    ): array|false {
        $this->setError([]);
        $where['entityStatus'] ??= EntityStatus::Active;

        if ($limit > 100) {
            $limit = 100;
        }

        if (($this instanceof HasSearchParams) && !empty($search)) {
            $searchParams = $this->getSearchParams($search);
        }

        try {
            return $this->repository->list($fields, $where, $orderBy, $limit, $page, $searchParams ?? []);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function changeEntityStatus(int $id, EntityStatus $status): bool {
        $this->setError([]);
        $entity = $this->getById($id, ['id', 'entityStatus']);

        if ($entity === false) {
            return false;
        } elseif ($entity->entityStatus === $status) {
            return true;
        } elseif ($entity->entityStatus === EntityStatus::Deleted) {
            return $this->setError(
                'Entidades excluídas não podem ter seu status alterado.',
                StatusCode::Forbidden
            );
        }

        try {
            return $this->repository->changeEntityStatus($entity, $status);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function getError(): array|string {
        return $this->error ?? [];
    }

    protected function setError(array|string $error, StatusCode|int|string $code = StatusCode::InternalServerError): false {
        $this->error = $error;
        $this->code = $code instanceof StatusCode
            ? $code
            : (StatusCode::tryFrom((int)$code) ?? StatusCode::InternalServerError);

        return false;
    }

    public function getErrorAsString(): string {
        if (!is_array($this->error)) {
            return $this->error;
        }

        $lines = [];
        array_walk_recursive($this->error, function ($value, $key) use (&$lines) {
            $lines[] = "$key: $value";
        });

        return implode(PHP_EOL, $lines);
    }

    protected function delete(int $id): bool {
        $entity = $this->getById($id, ['id']);

        if ($entity === false) {
            return false;
        }

        try {
            $this->repository->delete($entity);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        return true;
    }

}