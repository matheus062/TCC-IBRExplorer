<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Validator\EntityValidator;

class ZipCode extends ValueObject {

    public function jsonSerialize(): string {
        return $this->value;
    }

    protected function validate(): bool {
        $this->value = Strings::onlyNumbers($this->value);

        if (strlen($this->value) !== 8) {
            $this->messages = EntityValidator::ENTITY_FIELD_INVALID;
        }

        return empty($this->messages);
    }

}