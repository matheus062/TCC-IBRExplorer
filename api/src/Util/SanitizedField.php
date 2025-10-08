<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Validator\EntityValidator;

class SanitizedField extends ValueObject {

    public function jsonSerialize(): string {
        return $this->value;
    }

    protected function validate(): bool {
        $value = $this->removeAccents($this->value);
        $value = strtolower($value);
        $value = str_replace(' ', '-', $value);
        $value = preg_replace('/[^a-z0-9\-]/', '', $value);
        $value = preg_replace('/-+/', '-', $value);
        $value = trim($value, '-');
        $this->value = $value;

        if (empty($this->value)) {
            $this->messages = EntityValidator::ENTITY_FIELD_INVALID;
        }

        return empty($this->messages);
    }

    private function removeAccents(string $value): string {
        /** @noinspection RegExpSingleCharAlternation */
        return preg_replace(
            [
                '/á|à|ã|â|ä/', '/Á|À|Ã|Â|Ä/',
                '/é|è|ê|ë/', '/É|È|Ê|Ë/',
                '/í|ì|î|ï/', '/Í|Ì|Î|Ï/',
                '/ó|ò|õ|ô|ö/', '/Ó|Ò|Õ|Ô|Ö/',
                '/ú|ù|û|ü/', '/Ú|Ù|Û|Ü/',
                '/ñ/', '/Ñ/',
                '/ç/', '/Ç/',
            ],
            [
                'a', 'A',
                'e', 'E',
                'i', 'I',
                'o', 'O',
                'u', 'U',
                'n', 'N',
                'c', 'C',
            ],
            $value
        );
    }

    public function __toString(): string {
        return $this->value;
    }

}
