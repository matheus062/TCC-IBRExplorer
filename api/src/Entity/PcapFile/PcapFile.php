<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\PcapFile;

use Brick\Math\BigDecimal;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\PcapFile\PcapFileStatus;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Util\File;

class PcapFile extends Entity {

    public PcapFileStatus $status;
    public ?File $file;
    public ?int $fileSize;
    public BigDecimal $processed;
    public ?Pcap $pcap;

    public function setData(array $data): void {
        parent::setData($data);

        if ($this->isNew()) {
            $this->status ??= PcapFileStatus::WaitingUpload;
            $this->processed ??= BigDecimal::zero();

            if (!empty($this->file)) {
                $this->key ??= Entity::generateKey();
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

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'status' => PcapFileStatus::class,
            default => parent::isEnum($field)
        };
    }

    protected function isDecimal(string $field): bool {
        return ($field === 'processed') || parent::isDecimal($field);
    }

}