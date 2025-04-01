<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Entity\Enum\System\FileExt;
use IBRExplorer\Entity\Enum\System\FileType;
use IBRExplorer\Validator\EntityValidator;

class File extends ValueObject {

    public string $name;
    public FileType $type;
    public FileExt $ext;
    public ?string $data;

    public function jsonSerialize(bool $database = false): array {
        $serialize = [
            'name' => $this->name,
            'type' => $this->type->value,
            'ext' => $this->ext->value
        ];

        if ($database || empty($this->data)) {
            return $serialize;
        }

        $serialize['data'] = $this->data;

        return $serialize;
    }

    protected function validate(): bool {
        $data = (is_string($this->value)) ? json_decode($this->value, true) : $this->value;

        if (!empty($data)) {
            foreach ($data as $field => $value) {
                switch ($field) {
                    case 'type':
                        $value = FileType::tryFrom((int)$value);

                        if (empty($value)) {
                            $this->messages['type'] = EntityValidator::ENTITY_FIELD_INVALID;

                            continue 2;
                        }

                        break;
                    case 'ext':
                        $value = ($value instanceof FileExt) ? $value : FileExt::tryFrom($value);

                        if (empty($value)) {
                            $this->messages['ext'] = EntityValidator::ENTITY_FIELD_INVALID;

                            continue 2;
                        }

                        break;
                    default:
                }

                $this->$field = $value;
            }

            $this->value = [];
        }

        if (empty($this->name)) {
            $this->messages['name'] = EntityValidator::ENTITY_FIELD_REQUIRED;
        }

        if (empty($this->ext)) {
            $this->messages['ext'] = EntityValidator::ENTITY_FIELD_REQUIRED;
        }

        $this->type ??= FileType::Document;

        return true;
    }
}