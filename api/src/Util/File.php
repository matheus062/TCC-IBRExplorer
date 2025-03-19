<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Entity\Enum\System\FileType;

class File extends ValueObject {

    public string $name;
    public FileType $type;
    public string $ext;
    public ?string $data;

    public function jsonSerialize(): array {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'ext' => $this->ext
        ];
    }

    protected function validate(): bool {
        // TODO: Implementar validação de arquivo.
        return true;
    }
}