<?php

declare(strict_types=1);

namespace IBRExplorer\Service;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Repository\EntityRepository;
use IBRExplorer\Repository\Exception\DuplicateEntityException;
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

        if ($this instanceof HasSearchParams) {
            $searchParams = $this->getSearchParams($search);
        }

        try {
            return $this->repository->list($fields, $where, $orderBy, $limit, $page, $searchParams ?? []);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function getCode(): StatusCode {
        return $this->code ?? StatusCode::Ok;
    }

    public function create(Entity|array $data): int|false {
        $this->setError([]);

        if (is_array($data)) {
            unset($data['id']);
            /** @var Entity $entity */
            $entity = new $this->repository->entityClass($data);
        } else {
            $entity = $data;
            $entity->setId(0);
        }

        if (!$this->validator->isValid($entity)) {
            return $this->setError($this->validator->getMessages(), StatusCode::InvalidEntity);
        }

        try {
            $this->repository->save($entity);
        } catch (DuplicateEntityException $e) {
            return $this->setError([
                'error' => $e->getMessage(),
                'field' => $e->field
            ], $e->getCode());
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        return $entity->id;
    }

    public function update(int $id, Entity|array $data): bool {
        $this->setError([]);
        $currentEntity = $this->getById($id);

        if ($currentEntity === false) {
            return false;
        }

        if (is_array($data)) {
            $data['id'] = $id;
            /** @var Entity $entity */
            $entity = new $this->repository->entityClass($data);
        } else {
            $entity = clone $data;
            $entity->setId($id);
        }

        unset($data);
        $this->validator->setCurrentEntity($currentEntity);

        if (!$this->validator->isValid($entity)) {
            return $this->setError($this->validator->getMessages(), StatusCode::InvalidEntity);
        }

        try {
            return $this->repository->save($entity);
        } catch (DuplicateEntityException $e) {
            return $this->setError([
                'error' => $e->getMessage(),
                'field' => $e->field
            ], $e->getCode());
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function getById(int $id, array $fields = ['*']): Entity|false {
        $this->setError([]);

        try {
            $entity = $this->repository->read($id, $fields);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        if (($entity === false) || ($entity->entityStatus === EntityStatus::Deleted)) {
            return $this->setError('Registro não localizado.', StatusCode::NotFound);
        }

        return $entity;
    }

    public function changeStatus(int $id, EntityStatus $status): bool {
        $this->setError([]);
        $entity = $this->getById($id, ['id', 'entityStatus']);

        if ($entity === false) {
            return false;
        } elseif ($entity->entityStatus === $status) {
            return true;
        } elseif ($entity->entityStatus === EntityStatus::Deleted) {
            $this->setError('Entidades excluídas não podem ter seu status alterado.', StatusCode::Forbidden);
        }

        try {
            return $this->repository->changeStatus($entity, $status);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function getError(): array|string {
        return $this->error ?? [];
    }

    protected function setError(array|string $error, StatusCode|int $code = StatusCode::InternalServerError): false {
        $this->error = $error;
        $this->code = is_int($code)
            ? (StatusCode::tryFrom($code) ?? StatusCode::InternalServerError)
            : $code;

        return false;
    }

}