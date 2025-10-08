<?php

namespace IBRExplorer\Api\Trait;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Util\File;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;

trait RouteRespondFileTrait {

    protected function respondWithFile(
        File           $file,
        StatusCode|int $statusCode = StatusCode::Ok,
        bool           $decodeBase64 = true
    ): Response {
        $response = !empty($this->response) ? $this->response : (new ResponseFactory())->createResponse();

        if (is_int($statusCode)) {
            $statusCode = StatusCode::tryFrom($statusCode) ?? StatusCode::InternalServerError;
        } elseif (empty($file->ext) || empty($file->data)) {
            throw new RuntimeException('O arquivo enviado para resposta não possui os dados necessários.');
        }

        $name = $file->name ?? time();
        $ext = $file->ext->value ?? '';
        $data = ($decodeBase64) ? base64_decode($file->data, true) : $file->data;
        $contentType = $file->getContentTypeByExt();

        $response->getBody()->write($data);

        return $response
            ->withHeader('Content-Type', $contentType->value)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '.' . $ext . '"')
            ->withHeader('Content-Length', (string)strlen($data))
            ->withHeader('Access-Control-Expose-Headers', 'Content-Disposition')
            ->withStatus($statusCode->value);
    }

}