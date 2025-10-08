<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Entity;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class EntityUpdateAction extends EntityAction {

    protected function run(): Response {
        $id = (int)($this->arguments['id'] ?? 0);

        if (empty($id)) {
            return $this->respond('O `id` da entidade é obrigatório.', StatusCode::BadRequest);
        } elseif (empty($this->body)) {
            return $this->respond('O corpo da requisição é obrigatório.', StatusCode::BadRequest);
        } elseif (!empty($this->arguments)) {
            foreach ($this->arguments as $key => $argument) {
                unset($this->body[$key]); // Remove do corpo para não alterar o Parent
            }
        }

        $result = $this->entityService->update($id, $this->body);

        if ($result === false) {
            return $this->respondWithServiceError();
        } elseif (!empty($this->entityService->getError())) {
            return $this->respond([
                'message' => 'Registro atualizado com erros.',
                'errors' => $this->entityService->getError()
            ]);
        }

        return $this->respond(['message' => 'Registro atualizado com sucesso!']);
    }
}