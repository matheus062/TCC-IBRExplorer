<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;

class PcapFileStartUploadAction extends PcapFileAction {

    protected function run(): Response {
        // TODO: Criar o registro no banco de dados e retornar um link para um armazenamento S3.
        //  Assim envia o arquivo direto pro S3, onde a API irá baixar depois (se possível por blocos) para o processamento do PCAP
        // 1.	[Client] Uppy inicia o upload.
        // 2.	[Client → API] Uppy faz uma requisição GET para sua API solicitando credenciais temporárias de upload para o S3 (pré-assinadas).
        // 3.	[API] Gera e retorna a URL pré-assinada (e cabeçalhos necessários).
        // 4.	[Client → S3] Uppy usa essa URL para enviar diretamente o chunk para o S3.
        // 5.	[Client → API] Ao final, Uppy pode chamar uma rota de “upload finalizado” para sua API saber que o processo terminou.
        // 6.	[API] Atualiza o status do arquivo, registra metadados, etc.

        $name = $this->body['name'] ?? null;
        $ext = $this->body['ext'] ?? null;

        if (empty($name) || empty($ext)) {
            return $this->respond(
                'Necessário enviar `name` e `ext` do arquivo para solicitar o link.',
                StatusCode::BadRequest
            );
        }

        $response = $this->entityService->createWithS3Url($name, $ext);

        if ($response === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($response, StatusCode::Created);
    }
}