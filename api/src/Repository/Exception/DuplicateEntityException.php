<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Exception;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use Throwable;

class DuplicateEntityException extends Exception {

    public readonly string $field;

    public function __construct(
        string     $message = "",
        StatusCode $code = StatusCode::Conflict,
        string     $field = "",
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code->value, $previous);

        $this->field = $field;
    }

}