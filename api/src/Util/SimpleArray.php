<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Validator\EntityValidator;

class SimpleArray extends ValueObject {

    public function jsonSerialize(): array {
        return $this->value;
    }

    protected function validate(): bool {
        $data = (is_string($this->value)) ? json_decode($this->value, true) : $this->value;

        if (empty($data)) {
            $this->messages['value'] = EntityValidator::ENTITY_FIELD_REQUIRED;

            return false;
        }

        $this->value = $data;

        return true;
    }

}