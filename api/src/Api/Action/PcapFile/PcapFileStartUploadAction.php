<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileStartUploadAction extends PcapFileAction {

    protected function run(): Response {
        $name = $this->body['name'] ?? null;
        $ext = $this->body['ext'] ?? null;
        $visibility = $this->body['visibility'] ?? null;

        if (empty($name) || empty($ext)) {
            return $this->respond(
                'Necessário enviar `name` e `ext` do arquivo para solicitar o link.',
                StatusCode::BadRequest
            );
        }

        $response = $this->entityService->createUploadRequest($name, $ext, $visibility);

        if ($response === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($response, StatusCode::Created);
    }
}
