<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Exception;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use Throwable;

class InvalidFileDataException extends Exception {

    public readonly string $field;

    public function __construct(
        string     $message = "Campo `data` do arquivo não informado ou inválido.",
        StatusCode $code = StatusCode::BadRequest,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code->value, $previous);
    }

}