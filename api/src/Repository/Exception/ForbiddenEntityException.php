<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Exception;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use Throwable;

class ForbiddenEntityException extends Exception {

    public function __construct(
        string     $message = "Entidade com campos proíbidos para salvar.",
        StatusCode $code = StatusCode::Forbidden,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code->value, $previous);
    }


}