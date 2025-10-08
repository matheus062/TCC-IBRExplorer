<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Exception;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use Throwable;

class InvalidDeleteException extends Exception {

    public function __construct(
        string     $message = "Somente entidades com o campo `id` informado podem ser deletadas.",
        StatusCode $code = StatusCode::InternalServerError,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code->value, $previous);
    }

}