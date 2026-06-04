<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

class JsonField extends ValueObject {

    public function jsonSerialize(): array {
        return $this->value;
    }

    protected function validate(): bool {
        $data = (is_string($this->value)) ? json_decode($this->value, true) : $this->value;

        $this->value = is_array($data) ? $data : [];

        return true;
    }

}
