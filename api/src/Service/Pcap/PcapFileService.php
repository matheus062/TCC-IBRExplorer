<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use DateTime;
use Exception;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\PcapFile\PcapFileStatus;
use IBRExplorer\Entity\Enum\PcapFile\PcapFileVisibility;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Service\EntityService;
use IBRExplorer\Service\Interface\HasProcessBeforeSave;
use IBRExplorer\Storage\FileSystem;
use IBRExplorer\Validator\PcapFile\PcapFileValidator;

class PcapFileService extends EntityService implements HasProcessBeforeSave {

    private const int LOCAL_UPLOAD_CHUNK_BYTES = 10485760;
    private const int MAX_UPLOAD_FILE_BYTES = 157286400;

    public function __construct() {
        parent::__construct(PcapFile::class, new PcapFileValidator());
    }

    public function list(
        array  $fields = ['id', 'key'],
        array  $where = [],
        array  $orderBy = ['id DESC'],
        int    $limit = 15,
        int    $page = 1,
        string $search = ''
    ): array|false {
        $where['createdBy'] = PostgreSQL::$instance->getUser()->id;

        return parent::list($fields, $where, $orderBy, $limit, $page, $search);
    }

    public function listPublic(
        array  $fields = ['id', 'key'],
        array  $where = [],
        array  $orderBy = ['id DESC'],
        int    $limit = 15,
        int    $page = 1,
        string $search = ''
    ): array|false {
        $where['visibility'] = PcapFileVisibility::Public;

        return parent::list($fields, $where, $orderBy, $limit, $page, $search);
    }

    public function processBeforeSave(Entity $entity): void {
        if (!$entity->isNew()) {
            return;
        }

        if (!empty($entity->file)) {
            $entity->file->data = null;
        }

        unset($entity->fileSize);
        unset($entity->pcap);
        unset($entity->uploadedAt);
        unset($entity->processStartedAt);
        unset($entity->processFinishedAt);
        unset($entity->processError);
    }

    public function createUploadRequest(string $fileName, string $fileExt, mixed $visibility = null): array|false {
        try {
            $useS3Storage = $this->isS3StorageEnabled();
            $pcapFile = new PcapFile([
                'visibility' => $this->normalizeVisibility($visibility),
                'file' => [
                    'name' => $fileName,
                    'altName' => $fileName,
                    'ext' => $fileExt,
                    's3Store' => $useS3Storage,
                ],
            ]);

            if (!$this->validator->isValid($pcapFile)) {
                return $this->setError($this->validator->getMessages(), StatusCode::InvalidEntity);
            }

            $upload = $useS3Storage
                ? FileSystem::getInstance()->createSignedUrlBucketS3($pcapFile->fileEntityPath(), $pcapFile->file)
                : [
                    'method' => 'POST',
                    'mode' => 'api',
                    'chunkSize' => self::LOCAL_UPLOAD_CHUNK_BYTES,
                ];
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        $id = $this->create($pcapFile);

        if ($id === false) {
            return false;
        }

        return [
            'id' => $id,
            'key' => $pcapFile->key,
            'status' => $pcapFile->status->value,
            'storage' => [
                'mode' => $useS3Storage ? 's3' : 'local',
                'chunkSize' => $useS3Storage ? null : self::LOCAL_UPLOAD_CHUNK_BYTES,
                'chunkEndpoint' => $useS3Storage ? null : '/pcap/file/' . $pcapFile->key . '/chunk',
                'confirmEndpoint' => '/pcap/file/' . $pcapFile->key . '/confirm',
            ],
            'upload' => $upload,
            'limits' => [
                'maxFileSize' => self::MAX_UPLOAD_FILE_BYTES,
            ],
        ];
    }

    /**
     * @throws Exception
     */
    private function isS3StorageEnabled(): bool {
        return $this->normalizeBoolean(PostgreSQL::$instance->columnById('system_config', 's3Storage', 1));
    }

    private function normalizeBoolean(mixed $value): bool {
        return match ($value) {
            true, 1, '1', 'true', 'TRUE', 't', 'T', 'yes', 'on' => true,
            default => false,
        };
    }

    private function normalizeVisibility(mixed $value): PcapFileVisibility {
        return match ($value) {
            2, '2', 'public', 'PUBLIC', 'Public' => PcapFileVisibility::Public,
            default => PcapFileVisibility::Private,
        };
    }

    public function saveUploadChunk(PcapFile $file, int $chunkIndex, int $totalChunks, string $data): bool {
        $this->setError([]);

        if ($this->isFileStoredInS3($file)) {
            return $this->setError(
                'O arquivo está configurado para envio direto ao S3 e não aceita chunks locais.',
                StatusCode::Conflict
            );
        } elseif (($file->status !== PcapFileStatus::WaitingUpload) && ($file->status !== PcapFileStatus::Uploaded)) {
            return $this->setError(
                'O arquivo não está em um estado válido para receber chunks.',
                StatusCode::Conflict
            );
        }

        $binaryChunk = base64_decode($data, true);

        if ($binaryChunk === false) {
            return $this->setError('Os dados enviados para o chunk são inválidos.', StatusCode::BadRequest);
        }

        try {
            $tempPath = $this->getChunkTempPath($file);
            $tempDirectory = dirname($tempPath);

            if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0755, true)) {
                throw new Exception('Não foi possível criar o diretório temporário do upload.');
            }

            if (($chunkIndex === 0) && file_exists($tempPath)) {
                unlink($tempPath);
            } elseif (($chunkIndex > 0) && !file_exists($tempPath)) {
                return $this->setError(
                    'A sequência dos chunks está inconsistente. Reinicie o envio do arquivo.',
                    StatusCode::Conflict
                );
            }

            if (file_put_contents($tempPath, $binaryChunk, FILE_APPEND) === false) {
                throw new Exception('Não foi possível persistir o chunk recebido.');
            } elseif ($chunkIndex !== ($totalChunks - 1)) {
                return true;
            }

            $targetPath = FileSystem::getInstance()->getAbsolutePath($file->fileEntityPath(), $file->file);
            $targetDirectory = dirname($targetPath);

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true)) {
                throw new Exception('Não foi possível criar o diretório final do upload.');
            } elseif (file_exists($targetPath)) {
                unlink($targetPath);
            }

            if (!rename($tempPath, $targetPath)) {
                throw new Exception('Não foi possível mover o arquivo finalizado para o diretório definitivo.');
            }

            $fileSize = filesize($targetPath);

            if ($fileSize === false) {
                throw new Exception('Não foi possível obter o tamanho do arquivo enviado.');
            } elseif ($fileSize > self::MAX_UPLOAD_FILE_BYTES) {
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }

                return $this->setError(
                    'O arquivo excede o limite máximo permitido de 150 MB.',
                    StatusCode::BadRequest
                );
            }

            return $this->markFileAsUploaded($file, $fileSize);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    private function isFileStoredInS3(PcapFile $file): bool {
        return $this->normalizeBoolean($file->file?->s3Store ?? false);
    }

    private function getChunkTempPath(PcapFile $file): string {
        return FileSystem::getInstance()->getFilesPath() . '.tmp/pcap_upload_' . $file->key . '.part';
    }

    private function markFileAsUploaded(PcapFile $file, int $fileSize): bool {
        $updated = parent::update($file->id, [
            'status' => PcapFileStatus::Uploaded->value,
            'uploadedAt' => (new DateTime())->format('Y-m-d H:i:s'),
            'fileSize' => $fileSize,
            'file' => $file->file->jsonSerialize(),
        ]);

        if ($updated) {
            $file->status = PcapFileStatus::Uploaded;
            $file->uploadedAt = new DateTime();
            $file->fileSize = $fileSize;
        }

        return $updated;
    }

    public function update(int $id, array|Entity $data): bool {
        /** @var PcapFile|false $current */
        $current = $this->getById($id);

        if ($current === false) {
            return false;
        }

        /** @var $current $entity */
        $entity = is_array($data)
            ? new $this->repository->entityClass($data)
            : $data;
        $entity->setId($id);

        if (!PostgreSQL::$instance->getUser()->checkUserHasRole(UserRoleType::System)) {
            unset($entity->file);
            unset($entity->fileSize);
            unset($entity->processed);
            unset($entity->pcap);
            unset($entity->uploadedAt);
            unset($entity->processStartedAt);
            unset($entity->processFinishedAt);
            unset($entity->processAttempts);
            unset($entity->processError);
        }

        return parent::update($id, $data);
    }

    /**
     * @param int $id
     * @param array $fields
     * @param bool $getFileData
     * @return PcapFile|false
     */
    public function getById(int $id, array $fields = ['*'], bool $getFileData = false): Entity|false {
        return parent::getById($id, $fields, $getFileData);
    }

    public function confirmUpload(PcapFile $file, ?int $declaredFileSize = null): array|false {
        $this->setError([]);

        try {
            if (empty($declaredFileSize) || ($declaredFileSize <= 0)) {
                return $this->setError(
                    'Necessário informar o tamanho final do arquivo para confirmar o upload.',
                    StatusCode::BadRequest
                );
            } elseif ($declaredFileSize > self::MAX_UPLOAD_FILE_BYTES) {
                return $this->setError(
                    'O arquivo excede o limite máximo permitido de 150 MB.',
                    StatusCode::BadRequest
                );
            }

            if ($this->isFileStoredInS3($file) && ($file->status === PcapFileStatus::WaitingUpload)) {
                $actualFileSize = FileSystem::getInstance()->getFileSize($file->fileEntityPath(), $file->file);

                if ($actualFileSize !== $declaredFileSize) {
                    return $this->setError(
                        'O tamanho informado não corresponde ao arquivo salvo no storage.',
                        StatusCode::Conflict
                    );
                } elseif (!$this->markFileAsUploaded($file, $actualFileSize)) {
                    return false;
                }
            } elseif (!$this->isFileStoredInS3($file) && ($file->status === PcapFileStatus::WaitingUpload)) {
                return $this->setError(
                    'O upload local ainda não foi concluído. Envie todos os chunks antes de confirmar.',
                    StatusCode::Conflict
                );
            }

            if ($file->status === PcapFileStatus::WaitingProcess) {
                return [
                    'id' => $file->id,
                    'key' => $file->key,
                    'status' => $file->status->value,
                    'message' => 'Arquivo já está aguardando processamento.'
                ];
            } elseif ($file->status !== PcapFileStatus::Uploaded) {
                return $this->setError(
                    'O arquivo não está em um estado válido para entrar na fila de processamento.',
                    StatusCode::Conflict
                );
            }

            $actualFileSize = FileSystem::getInstance()->getFileSize($file->fileEntityPath(), $file->file);

            if ($actualFileSize !== $declaredFileSize) {
                return $this->setError(
                    'O tamanho informado não corresponde ao arquivo final armazenado.',
                    StatusCode::Conflict
                );
            } elseif (!empty($file->fileSize) && ($file->fileSize !== $actualFileSize)) {
                return $this->setError(
                    'O tamanho persistido do arquivo está inconsistente com o storage.',
                    StatusCode::Conflict
                );
            }

            $updated = parent::update($file->id, [
                'status' => PcapFileStatus::WaitingProcess->value,
                'processError' => null,
            ]);

            if (!$updated) {
                return false;
            }

            return [
                'id' => $file->id,
                'key' => $file->key,
                'status' => PcapFileStatus::WaitingProcess->value,
                'message' => 'Arquivo confirmado e enviado para a fila de processamento.'
            ];
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function retryProcessing(PcapFile $file): array|false {
        $this->setError([]);

        try {
            if ($file->status === PcapFileStatus::WaitingProcess) {
                return [
                    'id' => $file->id,
                    'key' => $file->key,
                    'status' => $file->status->value,
                    'message' => 'Arquivo já está aguardando processamento.'
                ];
            } elseif ($file->status !== PcapFileStatus::Error) {
                return $this->setError(
                    'Apenas arquivos com erro de processamento podem ser reprocessados.',
                    StatusCode::Conflict
                );
            } elseif (empty($file->file) || empty($file->fileSize)) {
                return $this->setError(
                    'Arquivo sem metadados suficientes para reprocessamento.',
                    StatusCode::Conflict
                );
            }

            $updated = parent::update($file->id, [
                'status' => PcapFileStatus::WaitingProcess->value,
                'processed' => '0.00',
                'processStartedAt' => null,
                'processFinishedAt' => null,
                'processError' => null,
            ]);

            if (!$updated) {
                return false;
            }

            return [
                'id' => $file->id,
                'key' => $file->key,
                'status' => PcapFileStatus::WaitingProcess->value,
                'message' => 'Arquivo reenviado para processamento.'
            ];
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function claimNextForWorker(): PcapFile|false {
        $this->setError([]);
        $db = PostgreSQL::$instance;
        $db->beginTransaction();

        try {
            $db->execute('
                SELECT *
                FROM "pcap_file"
                WHERE "entityStatus" = ?
                  AND "status" = ?
                ORDER BY "createdAt"
                FOR UPDATE SKIP LOCKED
                LIMIT 1
            ', [
                EntityStatus::Active->value,
                PcapFileStatus::WaitingProcess->value
            ]);
            $row = $db->getLastStatement()?->fetch();

            if ($row === false) {
                $db->commit();

                return false;
            }

            $now = (new DateTime())->format('Y-m-d H:i:s');
            $attempts = ((int)($row['processAttempts'] ?? 0)) + 1;
            $pcapFile = new PcapFile($row);
            $pcapFile->setData([
                'status' => PcapFileStatus::Processing,
                'processed' => '0.00',
                'processStartedAt' => $now,
                'processFinishedAt' => null,
                'processAttempts' => $attempts,
                'processError' => null,
            ]);

            if (!$this->saveEntity($pcapFile)) {
                throw new Exception($this->getErrorAsString(), $this->getCode()->value);
            }

            $pcapFile->setData([
                'key' => $row['key'],
                'createdAt' => $row['createdAt'],
                'createdBy' => $row['createdBy'],
            ]);

            $db->commit();

            return $pcapFile;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function updateWorkerProgress(int $id, float $progress): bool {
        $progress = max(0, min(100, $progress));
        $this->setError([]);

        try {
            return PostgreSQL::$instance->updateRow('pcap_file', [
                'processed' => number_format($progress, 2, '.', ''),
                'updatedAt' => (new DateTime())->format('Y-m-d H:i:s'),
                'updatedBy' => PostgreSQL::$instance->getUser()->id,
            ], $id);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function markWorkerProcessed(int $id): bool {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->setError([]);

        try {
            return PostgreSQL::$instance->updateRow('pcap_file', [
                'status' => PcapFileStatus::Processed->value,
                'processed' => '100.00',
                'processFinishedAt' => $now,
                'processError' => null,
                'updatedAt' => $now,
                'updatedBy' => PostgreSQL::$instance->getUser()->id,
            ], $id);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function markWorkerError(int $id, string $message): bool {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->setError([]);

        try {
            return PostgreSQL::$instance->updateRow('pcap_file', [
                'status' => PcapFileStatus::Error->value,
                'processFinishedAt' => $now,
                'processError' => $message,
                'updatedAt' => $now,
                'updatedBy' => PostgreSQL::$instance->getUser()->id,
            ], $id);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

}
