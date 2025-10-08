<?php

declare(strict_types=1);

namespace IBRExplorer\Entity;

use BackedEnum;
use Brick\Math\BigDecimal;
use DateTime;
use Exception;
use IBRExplorer\Cache\Entity\EntityMetadata;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Util\ValueObject;
use JsonSerializable;
use ReflectionEnum;
use Throwable;

abstract class Entity implements JsonSerializable {

    protected const array SENSIBLE_FIELDS = ['password'];

    public int $id;
    public EntityStatus $entityStatus;
    public string $key;
    public DateTime $createdAt;
    public User $createdBy;
    public DateTime $updatedAt;
    public User $updatedBy;
    public ?string $internalNotes;
    // public ?WebMetadata $webMetadata;
    public null $webMetadata;

    protected array $messages = [];

    private bool $isNew = true;

    public function __construct(array|int|string $dataOrId) {
        if (!is_array($dataOrId)) {
            $this->setId((int)$dataOrId);

            return;
        } elseif (!empty($dataOrId['id'])) {
            $this->setId((int)$dataOrId['id']);
        }

        $this->isNew = empty($this->id);
        $this->setData($dataOrId);

        if ($this->isNew) {
            $this->entityStatus ??= EntityStatus::Active;
        }
    }

    public function setId(int $id): void {
        $this->id = $id;
        $this->isNew = empty($this->id);
    }

    public function setData(array $data): void {
        $entityMetadata = EntityMetadata::of($this::class);

        foreach ($data as $field => $value) {
            try {
                if (in_array($field, self::SENSIBLE_FIELDS) || !in_array($field, $entityMetadata->fields)) {
                    continue;
                } elseif (is_null($value)) {
                    if ($entityMetadata->fieldsMeta[$field]['nullable']) {
                        $this->$field = null;
                    }

                    continue;
                } elseif ($this->isDateTime($field)) {
                    $this->$field = ($value instanceof DateTime) ? (clone $value) : (new DateTime($value));
                } elseif ($this->isDecimal($field)) {
                    $this->$field = BigDecimal::of($value);
                } elseif (($entityClass = $this->isEntity($field)) !== null) {
                    if ($entityMetadata->relations[$field]['isMulti']) {
                        $this->$field = [];

                        foreach ($value as $index => $item) {
                            /** @var Entity $entity */
                            $entity = ($item instanceof Entity) ? $item : new $entityClass($item);
                            $messages = $entity->getMessages();

                            if (!empty($messages)) {
                                $this->messages[$field][$index] = $messages;
                            }

                            $this->$field[] = $entity;
                        }
                    } else {
                        /** @var Entity $entity */
                        $entity = ($value instanceof Entity) ? $value : new $entityClass($value);
                        $messages = $entity->getMessages();

                        if (!empty($messages)) {
                            $this->messages[$field] = $messages;
                        }

                        $this->$field = $entity;
                    }
                } elseif (($valueObjectClass = $this->isValueObject($field)) !== null) {
                    /** @var ValueObject $valueObject */
                    $valueObject = new $valueObjectClass();

                    if (!$valueObject->setValue($value)) {
                        $this->messages[$field] = $valueObject->getMessages();
                    }

                    $this->$field = $valueObject;
                } elseif (($enumClass = $this->isEnum($field)) !== null) {
                    if ($value instanceof BackedEnum) {
                        $this->$field = $value;
                    } else {
                        $reflectionEnum = new ReflectionEnum($enumClass);
                        $backingType = $reflectionEnum->getBackingType()->getName();
                        /** @noinspection PhpUndefinedMethodInspection */
                        $this->$field = ($backingType === 'int')
                            ? $enumClass::tryFrom((int)$value)
                            : $enumClass::tryFrom($value);
                    }
                } else {
                    $this->$field = match ($entityMetadata->fieldsMeta[$field]['type']) {
                        'int' => (int)$value,
                        'bool' => (bool)$value,
                        default => $value
                    };
                }
            } catch (Throwable $e) {
                $this->messages[$field] = 'Não foi possível inicializar o campo: ' . $e->getMessage();
            }
        }
    }

    protected function isDateTime(string $field): bool {
        return in_array($field, ['createdAt', 'updatedAt']);
    }

    protected function isDecimal(string $field): bool {
        return false;
    }

    public function isEntity(string $field): ?string {
        return match ($field) {
            'createdBy', 'updatedBy' => User::class,
            default => null,
        };
    }

    public function getMessages(): array {
        return $this->messages;
    }

    public function isValueObject(string $field): ?string {
        return null;
    }

    protected function isEnum(string $field): ?string {
        return match ($field) {
            'entityStatus' => EntityStatus::class,
            default => null,
        };
    }

    public static function generateKey(): string {
        try {
            return substr(bin2hex(random_bytes((int)ceil(8))), 0, 16);
        } catch (Exception) {
            return substr(md5(base64_encode((string)time())), 0, 16);
        }
    }

    public function isNew(): bool {
        return $this->isNew;
    }

    public function jsonSerialize(bool $database = false): array {
        $data = (array)$this;
        $entityMetadata = EntityMetadata::of($this::class);

        foreach ($data as $field => $value) {
            try {
                if (!in_array($field, $entityMetadata->fields)) {
                    unset($data[$field]);

                    continue;
                } elseif (is_null($value)) {
                    continue;
                }

                if (!empty($this->isEntity($field))) {
                    if (is_array($value)) {
                        foreach ($value as $index => $item) {
                            $data[$field][$index] = $item->jsonSerialize($database);
                        }
                    } else {
                        $data[$field] = $value->jsonSerialize($database);
                    }
                } elseif (!empty($this->isValueObject($field))) {
                    $data[$field] = $value->jsonSerialize($database);
                } elseif (!empty($this->isEnum($field))) {
                    $data[$field] = $value->value;
                } elseif (!empty($this->isDateTime($field))) {
                    $data[$field] = $value->format('Y-m-d H:i:s');
                } elseif (!empty($this->isDecimal($field))) {
                    $data[$field] = (string)$value;
                }
            } catch (Exception) {
                unset($data[$field]);

                continue;
            }
        }

        return $data;
    }
}