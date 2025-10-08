<?php

declare(strict_types=1);

namespace IBRExplorer\Storage;

use Aws\S3\S3Client;
use Exception;
use IBRExplorer\Storage\AwsS3\AwsS3FileSystem;
use IBRExplorer\Storage\AwsS3\Config\AwsS3FileSystemConfig;
use IBRExplorer\Util\File;
use RuntimeException;

class FileSystem {

    private static ?FileSystem $instance;

    private string $filesPath;
    private string $assetsPath;
    private ?AwsS3FileSystemConfig $s3Config;

    public function __construct(string $filesPath, string $assetsPath, ?AwsS3FileSystemConfig $s3Config) {
        $this->filesPath = $filesPath;
        $this->assetsPath = $assetsPath;
        $this->s3Config = $s3Config;
    }

    /**
     * @throws Exception
     */
    public function saveFile(string $entityPath, File $file): bool {
        return $file->s3Store
            ? AwsS3FileSystem::getInstance($this->s3Config)->saveFile($entityPath, $file)
            : $this->saveLocalFile($entityPath, $file);
    }

    public static function getInstance(
        ?string                $filesPath = null,
        ?string                $assetsPath = null,
        ?AwsS3FileSystemConfig $s3Config = null
    ): FileSystem {
        if (!isset(self::$instance)) {
            if (empty($filesPath) || empty($assetsPath)) {
                throw new RuntimeException('Sistema de arquivos não inicializado.');
            }

            self::$instance = new FileSystem($filesPath, $assetsPath, $s3Config);
        }

        return self::$instance;
    }

    /**
     * @throws Exception
     */
    private function saveLocalFile(string $entityPath, File $file): bool {
        $fullPath = $this->getFilesPath() . $entityPath . DIRECTORY_SEPARATOR . $file->name . '.' . $file->ext->value;

        $directory = dirname($fullPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new Exception('Não foi possível criar o diretório do arquivo.');
        }

        $fileData = base64_decode($file->data ?? '', true);

        if ($fileData === false) {
            throw new Exception('Dados contidos no arquivo inválidos.');
        }

        return (file_put_contents($fullPath, $fileData) !== false);
    }

    public function getFilesPath(): string {
        return $this->filesPath;
    }

    /**
     * @throws Exception
     */
    public function readFile(string $entityPath, File $file): void {
        $file->s3Store
            ? AwsS3FileSystem::getInstance($this->s3Config)->readFile($entityPath, $file)
            : $this->readLocalFile($entityPath, $file);
    }

    /**
     * @throws Exception
     */
    private function readLocalFile(string $entityPath, File $file): void {
        $fullPath = $this->getFilesPath() . $entityPath . DIRECTORY_SEPARATOR . $file->name . '.' . $file->ext->value;
        $file->data = base64_encode($this->readStringFile($fullPath));
    }

    /**
     * @throws Exception
     */
    private function readStringFile(string $fullPath): string {
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            throw new Exception('Arquivo não localizado ou sem permissão de leitura.');
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new Exception('Arquivo não localizado ou sem permissão de leitura.');
        }

        return $contents;
    }

    /**
     * @throws Exception
     */
    public function readAssetFile(string $filePath, string $fileName): string {
        return $this->readStringFile($this->getAssetsPath() . $filePath . DIRECTORY_SEPARATOR . $fileName);
    }

    public function getAssetsPath(): string {
        return $this->assetsPath;
    }

    /**
     * @throws Exception
     */
    public function createSignedUrlBucketS3(string $entityPath, File $file): array {
        if (empty($this->s3Config)) {
            throw new Exception('AWS S3 Bucket não configurado.');
        }

        $file->awsS3Key = 'uploads/' . $entityPath . '/' . $file->name . '.' . $file->ext->value;
        $contentType = $file->getContentTypeByExt()->value;
        $s3 = new S3Client([
            'region' => AWS_REGION,
            'version' => 'latest',
            'credentials' => [
                'key' => AWS_ACCESS_KEY,
                'secret' => AWS_SECRET_KEY,
            ]
        ]);

        // TODO: Validar se não precisa ser 'public-read' se quiser acesso público direto
        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => AWS_BUCKET,
            'Key' => $file->awsS3Key,
            'ContentType' => $contentType,
            'ACL' => 'private',
        ]);
        $request = $s3->createPresignedRequest($cmd, '+15 minutes');

        return [
            'method' => 'PUT',
            'url' => (string)$request->getUri(),
            'headers' => [
                'Content-Type' => $contentType
            ]
        ];
    }

}