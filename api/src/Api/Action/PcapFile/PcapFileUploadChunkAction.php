<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\PcapFile\PcapFile;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileUploadChunkAction extends PcapFileAction {

    protected function run(): Response {
        $key = (string)($this->arguments['key'] ?? '');
        /** @var PcapFile|false $file */
        $file = $this->entityService->getByKey($key, ['createdBy', 'file', 'status']);

        if ($file === false) {
            return $this->respondWithServiceError();
        } elseif (PostgreSQL::$instance->getUser()->id !== $file->createdBy->id) {
            return $this->respond(
                'Usuário sem permissão de carregar `chunk`.',
                StatusCode::Forbidden
            );
        }

        $chunk = (int)($this->body['chunk'] ?? -1);
        $totalChunks = (int)($this->body['chunks'] ?? 0);
        $data = (string)($this->body['data'] ?? '');

        if (($chunk < 0) || ($totalChunks < 1) || empty($data)) {
            return $this->respond(
                'Necessário enviar `chunk`, `chunks` e `data` para processar o envio local.',
                StatusCode::BadRequest
            );
        }

        $success = $this->entityService->saveUploadChunk($file, $chunk, $totalChunks, $data);

        if (!$success) {
            return $this->respondWithServiceError();
        }

        return $this->respond('Chunk recebido com sucesso.');
    }

}
