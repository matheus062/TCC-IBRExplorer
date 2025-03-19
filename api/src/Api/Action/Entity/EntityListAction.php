<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use Psr\Http\Message\ResponseInterface as Response;

class EntityListAction extends EntityAction {

    protected function run(): Response {
        $list = $this->entityService->list(
            $this->entityParams->fields,
            $this->entityParams->filters,
            $this->entityParams->orderBy,
            $this->entityParams->limit,
            $this->entityParams->page,
            $this->entityParams->search,
        );

        if ($list === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($list);
    }
}