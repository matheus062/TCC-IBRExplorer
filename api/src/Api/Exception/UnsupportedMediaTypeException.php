<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Exception;

use IBRExplorer\Api\Enum\StatusCode;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

class UnsupportedMediaTypeException extends HttpException {

    public function __construct(
        ServerRequestInterface $request,
        string                 $message = 'Formato de conteúdo (content-type) não suportado.',
        ?Throwable             $previous = null
    ) {
        parent::__construct($request, $message, StatusCode::UnsupportedMediaType->value, $previous);
    }


}