<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Middleware\ErrorHandler;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\Trait\RouteRespondTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ErrorHandler implements MiddlewareInterface {

    use RouteRespondTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(Throwable $exception): ResponseInterface {
        $responseBody = [
            'error' => DEBUG
                ? $exception->getMessage()
                : 'Ocorreu um erro desconhecido. Por favor, entre em contato com o suporte.',
        ];
        $statusCode = DEBUG
            ? StatusCode::tryFrom($exception->getCode()) ?? StatusCode::InternalServerError
            : StatusCode::InternalServerError;

        if (DEBUG) {
            $responseBody['exception'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        return $this->respond($responseBody, $statusCode);
    }

}
