<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Validator\EntityValidator;

class Email extends ValueObject {

    public function jsonSerialize(): string {
        return $this->value;
    }

    protected function validate(): bool {
        $email = filter_var($this->value, FILTER_SANITIZE_EMAIL);

        if ($email === false) {
            $this->messages = EntityValidator::ENTITY_FIELD_INVALID;
        } else {
            $this->value = $email;
        }

        return empty($this->messages);
    }
}