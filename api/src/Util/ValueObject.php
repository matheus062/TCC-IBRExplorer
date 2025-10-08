<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use JsonSerializable;

abstract class ValueObject implements JsonSerializable {

    protected array|string $value;
    protected array|string $messages = [];

    public function __construct(array|string|null $value = null) {
        if (!empty($value)) {
            $this->setValue($value);
        }
    }

    public function getValue(): array|string {
        return $this->value;
    }

    public function setValue(array|string $value): bool {
        $this->value = $value;

        return $this->validate();
    }

    public function getMessages(): array|string {
        return $this->messages;
    }

    public function __toString(): string {
        return $this->jsonSerialize();
    }

    abstract protected function validate(): bool;

}