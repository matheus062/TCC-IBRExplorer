<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class EntityReadAction extends EntityAction {

    protected function run(): Response {
        $id = (int)($this->arguments['id'] ?? 0);

        if (empty($id)) {
            return $this->respond('O `id` da entidade é obrigatório.', StatusCode::BadRequest);
        }

        $fields = $this->entityParams->fields ?: ['*'];
        $entity = $this->entityService->getById($id, $fields);

        if ($entity === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($entity);

    }
}