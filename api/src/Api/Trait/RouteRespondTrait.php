<?php

namespace IBRExplorer\Api\Trait;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Entity\Entity;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;

trait RouteRespondTrait {

    protected function respond(Entity|array|string $data, StatusCode|int $statusCode = StatusCode::Ok): Response {
        $response = !empty($this->response) ? $this->response : (new ResponseFactory())->createResponse();

        if (is_int($statusCode)) {
            $statusCode = StatusCode::tryFrom($statusCode) ?? StatusCode::InternalServerError;
        }

        if (is_string($data)) {
            $key = ($statusCode->value < 300) ? 'message' : 'error';
            $data = [$key => $data];
        }

        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode->value);
    }

}