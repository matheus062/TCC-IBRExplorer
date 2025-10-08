<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\System;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OptionsAction {

    final public function __invoke(Request $request, Response $response): Response {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type');
    }

}