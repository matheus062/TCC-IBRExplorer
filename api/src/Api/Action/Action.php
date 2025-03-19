<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action;

use IBRExplorer\Api\Enum\ContentType;
use IBRExplorer\Api\Exception\BadRequestException;
use IBRExplorer\Api\Exception\UnsupportedMediaTypeException;
use IBRExplorer\Api\Trait\RouteRespondTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpException;

abstract class Action {

    use RouteRespondTrait;

    protected Request $request;
    protected Response $response;
    protected array $headers;
    protected ?array $params;
    protected ?array $arguments = [];
    protected ?array $body = [];

    final public function __invoke(Request $request, Response $response): Response {
        $this->request = $request;
        $this->response = $response;

        try {
            $this->prepare();
        } catch (HttpException $e) {
            return $this->respond($e->getMessage(), $e->getCode());
        }

        return $this->run();
    }

    protected function prepare(): void {
        $this->headers = $this->getHeaders();
        $this->params = $this->request->getQueryParams();
        $this->arguments = $this->request->getAttributes();
        $this->body = $this->getBody();
    }

    private function getHeaders(): array {
        $headers = $this->request->getHeaders();

        if (
            in_array($this->request->getMethod(), ['POST', 'PUT']) &&
            (empty($headers['Content-Type']) || ($headers['Content-Type'][0] !== ContentType::Json->value))
        ) {
            throw new UnsupportedMediaTypeException($this->request);
        }

        return $headers;
    }

    private function getBody(): array|null {
        $body = $this->request->getBody()->getContents();

        if (empty($body)) {
            return null;
        }

        $data = json_decode($body, true);

        if (empty($data)) {
            throw new BadRequestException(
                $this->request,
                'Dados enviados no corpo da requisição são inválidos.'
            );
        }

        return $data;
    }

    abstract protected function run(): Response;

}