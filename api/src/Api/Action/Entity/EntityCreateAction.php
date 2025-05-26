<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class EntityCreateAction extends EntityAction {

    protected function run(): Response {
        if (empty($this->body)) {
            return $this->respond('O corpo da requisição é obrigatório.', StatusCode::BadRequest);
        } elseif (!empty($this->arguments)) {
            foreach ($this->arguments as $key => $argument) {
                $this->body[$key] = $argument; // Adiciona ao corpo para forçar o Parent
            }
        }

        $result = $this->entityService->create($this->body);

        if ($result === false) {
            return $this->respondWithServiceError();
        } elseif (!empty($this->entityService->getError())) {
            return $this->respond([
                'message' => 'Registro criado com erros.',
                'id' => $result,
                'errors' => $this->entityService->getError()
            ], StatusCode::Created);
        }

        return $this->respond([
            'message' => 'Registro criado com sucesso!',
            'id' => $result
        ], StatusCode::Created);
    }
}