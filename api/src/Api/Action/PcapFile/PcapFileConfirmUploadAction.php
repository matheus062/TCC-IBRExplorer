<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\PcapFile\PcapFile;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileConfirmUploadAction extends PcapFileAction {

    protected function run(): Response {
        $key = (string)($this->arguments['key'] ?? '');
        $declaredFileSize = isset($this->body['fileSize']) ? (int)$this->body['fileSize'] : null;

        if (empty($declaredFileSize) || ($declaredFileSize <= 0)) {
            return $this->respond(
                'Necessário enviar `fileSize` para confirmar o upload do arquivo.',
                StatusCode::BadRequest
            );
        }

        /** @var PcapFile|false $file */
        $file = $this->entityService->getByKey($key, ['createdBy', 'file', 'status', 'fileSize']);

        if ($file === false) {
            return $this->respondWithServiceError();
        } elseif (PostgreSQL::$instance->getUser()->id !== $file->createdBy->id) {
            return $this->respond(
                'Usuário sem permissão para confirmar o upload do arquivo.',
                StatusCode::Forbidden
            );
        }

        $result = $this->entityService->confirmUpload($file, $declaredFileSize);

        if ($result === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($result, StatusCode::Accepted);
    }
}
