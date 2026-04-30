<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\PcapFile;

use Brick\Math\BigDecimal;
use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\PcapFile\PcapFileStatus;
use IBRExplorer\Entity\Enum\PcapFile\PcapFileVisibility;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Util\File;

class PcapFile extends Entity {

    public PcapFileStatus $status;
    public ?File $file;
    public ?int $fileSize;
    public BigDecimal $processed;
    public ?Pcap $pcap;
    public PcapFileVisibility $visibility;
    public ?DateTime $uploadedAt;
    public ?DateTime $processStartedAt;
    public ?DateTime $processFinishedAt;
    public int $processAttempts;
    public ?string $processError;

    public function setData(array $data): void {
        parent::setData($data);

        if ($this->isNew()) {
            $this->status ??= PcapFileStatus::WaitingUpload;
            $this->processed ??= BigDecimal::zero();
            $this->visibility ??= PcapFileVisibility::Private;
            $this->processAttempts ??= 0;

            if (!empty($this->file)) {
                $this->key ??= Entity::generateKey();
                $this->file->altName ??= $this->file->name;
                $this->file->name = time() . '_' . $this->key;
            }
        }
    }

    public function isEntity(string $field): ?string {
        return match ($field) {
            'pcap' => Pcap::class,
            default => parent::isEntity($field)
        };
    }

    public function isValueObject(string $field): ?string {
        return match ($field) {
            'file' => File::class,
            default => parent::isValueObject($field)
        };
    }

    /**
     * @return string
     * @noinspection PhpUnused
     */
    public function fileEntityPath(): string {
        return 'pcap/' . $this->createdBy->id;
    }

    protected function isDecimal(string $field): bool {
        return ($field === 'processed') || parent::isDecimal($field);
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'status' => PcapFileStatus::class,
            'visibility' => PcapFileVisibility::class,
            default => parent::isEnum($field)
        };
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, [
                'uploadedAt',
                'processStartedAt',
                'processFinishedAt'
            ], true) || parent::isDateTime($field);
    }

}
