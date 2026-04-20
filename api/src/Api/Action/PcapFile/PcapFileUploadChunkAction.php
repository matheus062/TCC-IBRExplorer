<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\PcapFile\PcapFile;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileUploadChunkAction extends PcapFileAction {

    protected function run(): Response {
        $key = (string)($this->arguments['id'] ?? '');
        /** @var PcapFile|false $file */
        $file = $this->entityService->getByKey($key, ['createdBy']);

        if ($file === false) {
            return $this->respondWithServiceError();
        } elseif (PostgreSQL::$instance->getUser()->id !== $file->createdBy->id) {
            return $this->respond(
                'Usuário sem permissão de carregar `chunk`.', StatusCode::Forbidden
            );
        }

        $chunk = (int)($this->body['chunk'] ?? 0);
        $totalChunks = (int)($this->body['chunks'] ?? 1);
        $uploadedFile = $this->request->getUploadedFiles()['file'] ?? null;

        if (empty($uploadedFile) || ($uploadedFile->getError() !== UPLOAD_ERR_OK)) {
            return $this->respond('Ocorreu um erro ao enviar `chunk` do arquivo.', StatusCode::BadRequest);
        }

        $success = $this->entityService->saveFileChunk($file->id, $chunk, $totalChunks, $uploadedFile);

        if (!$success) {
            return $this->respondWithServiceError();
        }

        return $this->respond('Chunk recebido com sucesso.');
    }

}