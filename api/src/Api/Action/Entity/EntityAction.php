<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Action\EntityActionParams;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Service\EntityService;
use Psr\Http\Message\ResponseInterface as Response;

abstract class EntityAction extends Action {

    protected string $entityClass;
    protected EntityService $entityService;
    protected EntityActionParams $entityParams;

    protected function prepare(): void {
        parent::prepare();

        $this->entityClass ??= $this->arguments['entityClass'] ?? '';
        $this->entityService = IBRExplorerApi::getInstance()->getEntityService($this->entityClass);
        $this->entityParams = new EntityActionParams($this->entityClass, $this->params);
    }

    protected function respondWithServiceError(): Response {
        return $this->respond($this->entityService->getError(), $this->entityService->getCode());
    }

}