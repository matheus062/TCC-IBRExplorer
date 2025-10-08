<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use Psr\Http\Message\ResponseInterface as Response;

class EntityStatusDeleteAction extends EntityAction {

    protected function run(): Response {
        $id = (int)($this->arguments['id'] ?? 0);

        if (empty($id)) {
            return $this->respond('O `id` da entidade é obrigatório.', StatusCode::BadRequest);
        }

        $result = $this->entityService->changeEntityStatus($id, EntityStatus::Deleted);

        if ($result === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond(['message' => 'Registro deletado com sucesso!']);
    }

}