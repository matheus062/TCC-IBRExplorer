<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\PcapFile\PcapFile;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileRetryProcessingAction extends PcapFileAction {

    protected function run(): Response {
        $key = (string)($this->arguments['key'] ?? '');

        if (empty($key)) {
            return $this->respond('A `key` do arquivo é obrigatória.', StatusCode::BadRequest);
        }

        /** @var PcapFile|false $file */
        $file = $this->entityService->getByKey($key, [
            'createdBy',
            'file',
            'status',
            'fileSize',
            'processError',
        ]);

        if ($file === false) {
            return $this->respondWithServiceError();
        } elseif (PostgreSQL::$instance->getUser()->id !== $file->createdBy->id) {
            return $this->respond(
                'Usuário sem permissão para reprocessar o arquivo.',
                StatusCode::Forbidden
            );
        }

        $result = $this->entityService->retryProcessing($file);

        if ($result === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($result, StatusCode::Accepted);
    }
}
