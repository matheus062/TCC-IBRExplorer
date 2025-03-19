<?php

declare(strict_types=1);

namespace IBRExplorer\Util;


class Phone extends ValueObject {

    public int $countryCode;
    public int $areaCode;
    public string $number;

    public function jsonSerialize(): array {
        return [
            'countryCode' => $this->countryCode,
            'areaCode' => $this->areaCode,
            'number' => $this->number,
        ];
    }

    protected function validate(): bool {
        // TODO: Implementar a validação do telefone

        return true;
    }
}