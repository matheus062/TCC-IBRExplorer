<?php

declare(strict_types=1);

namespace IBRExplorer\Repository\Exception;

use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use Throwable;

class ParentFieldNullException extends Exception {

    public function __construct(
        string     $class,
        string     $field,
        string     $message = 'Houve um erro ao buscar a árvore genealógica da entidade.',
        StatusCode $code = StatusCode::InternalServerError,
        ?Throwable $previous = null
    ) {
        parent::__construct($message . ' ' . $class . ':' . $field, $code->value, $previous);
    }

}