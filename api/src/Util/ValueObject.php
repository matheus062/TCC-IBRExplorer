<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use JsonSerializable;

abstract class ValueObject implements JsonSerializable {

    protected string $value;
    protected array|string $messages = [];

    public function getValue(): string {
        return $this->value;
    }

    public function setValue(string $value): bool {
        $this->value = $value;

        return $this->validate();
    }

    abstract protected function validate(): bool;

    public function getMessages(): array|string {
        return $this->messages;
    }

    public function __toString(): string {
        return $this->jsonSerialize();
    }

}