<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Validator\EntityValidator;

class TaxId extends ValueObject {

    public function jsonSerialize(): string {
        return $this->value;
    }

    protected function validate(): bool {
        $this->value = preg_replace('/\D+/', '', $this->value);

        if (strlen($this->value) == 11) {
            $this->validateNaturalTaxId($this->value);
        } elseif (strlen($this->value) == 14) {
            $this->validateLegalTaxId($this->value);
        } else {
            $this->messages = EntityValidator::ENTITY_FIELD_INVALID;
        }

        return empty($this->messages);
    }

    private function validateNaturalTaxId(string $taxId): void {
        for ($t = 9; $t < 11; $t++) {
            $d = 0;

            for ($c = 0; $c < $t; $c++) {
                $d += $taxId[$c] * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;

            if ($taxId[$c] != $d) {
                $this->messages = EntityValidator::ENTITY_FIELD_INVALID;

                break;
            }
        }
    }

    private function validateLegalTaxId(string $taxId): void {
        $size = [5, 6];

        for ($t = 0; $t < 2; $t++) {
            $soma = 0;
            $pos = $size[$t];

            for ($i = 0; $i < 12 + $t; $i++) {
                $soma += $taxId[$i] * $pos;
                $pos = ($pos - 1 < 2) ? 9 : $pos - 1;
            }

            $digit = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);

            if ($taxId[12 + $t] != $digit) {
                $this->messages = EntityValidator::ENTITY_FIELD_INVALID;

                break;
            }
        }
    }

}