<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use IBRExplorer\Api\Enum\ContentType;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\FileExt;
use IBRExplorer\Entity\Enum\System\FileType;
use IBRExplorer\Validator\EntityValidator;
use RuntimeException;

class File extends ValueObject {

    public string $name;
    public ?string $altName;
    public FileType $type;
    public FileExt $ext;
    public ?string $data;
    public bool $s3Store;
    public ?string $awsS3Key;

    public function jsonSerialize(bool $database = false): array {
        $serialize = [
            'name' => $this->name,
            'type' => $this->type->value,
            'ext' => $this->ext->value,
            'contentType' => $this->getContentTypeByExt()
        ];

        if (!empty($this->altName)) {
            $serialize['altName'] = $this->altName;
        }

        if (!empty($this->awsS3Key)) {
            $serialize['awsS3Key'] = $this->awsS3Key;
        }

        if ($database) {
            $serialize['s3Store'] = $this->s3Store;

            if (!empty($this->awsS3Key)) {
                $serialize['awsS3Key'] = $this->awsS3Key;
            }

            return $serialize;
        }

        if (!empty($this->data)) {
            $serialize['data'] = $this->data;
        }

        return $serialize;
    }

    public function getContentTypeByExt(): ContentType {
        return match ($this->ext ?? null) {
            FileExt::ZIP => ContentType::Zip,
            FileExt::PDF => ContentType::Pdf,
            FileExt::XLSX => ContentType::Xlsx,
            FileExt::TXT => ContentType::Txt,
            FileExt::JPEG => ContentType::Jpeg,
            FileExt::PNG => ContentType::Png,
            FileExt::WEBP => ContentType::Webp,
            FileExt::MP4 => ContentType::Mp4,
            FileExt::PCAP => ContentType::Pcap,
            FileExt::PCAPNG => ContentType::Pcapng,
            default => throw new RuntimeException('Extensão não especificada nas condições do match.')
        };
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
                        if ($value === 'jpg') {
                            $value = 'jpeg';
                        }

                        $value = ($value instanceof FileExt) ? $value : FileExt::tryFrom($value);

                        if (empty($value)) {
                            $this->messages['ext'] = EntityValidator::ENTITY_FIELD_INVALID;

                            continue 2;
                        }

                        break;
                    default:
                }

                if (property_exists($this, $field)) {
                    $this->$field = $value;
                }
            }

            $this->value = [];
        }

        if (empty($this->ext)) {
            $this->messages['ext'] = EntityValidator::ENTITY_FIELD_REQUIRED;
        }

        $this->name ??= time() . '_' . Entity::generateKey();
        $this->type ??= $this->getTypeByExt();
        $this->s3Store ??= false;

        return empty($this->messages);
    }

    private function getTypeByExt(): FileType {
        return match ($this->ext ?? null) {
            FileExt::JPEG, FileExt::PNG, FileExt::WEBP => FileType::Image,
            FileExt::MP4 => FileType::Video,
            default => FileType::Document
        };
    }

}