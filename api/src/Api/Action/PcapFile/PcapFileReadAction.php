<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileReadAction extends PcapFileAction {

    protected function run(): Response {
        $raw = (string)($this->arguments['id'] ?? '');

        if (empty($raw)) {
            return $this->respond('O `id` da entidade é obrigatório.', StatusCode::BadRequest);
        }

        $fields = $this->entityParams->fields ?: ['*'];

        if (ctype_digit($raw)) {
            $entity = $this->entityService->getById((int)$raw, $fields, $this->entityParams->getFileData);
        } else {
            $entity = $this->entityService->getByKey($raw, $fields);
        }

        if ($entity === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($entity);
    }

}
