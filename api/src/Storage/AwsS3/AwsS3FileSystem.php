<?php

declare(strict_types=1);

namespace IBRExplorer\Storage\AwsS3;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;
use IBRExplorer\Storage\AwsS3\Config\AwsS3FileSystemConfig;
use IBRExplorer\Util\File;
use RuntimeException;

class AwsS3FileSystem {

    private static ?AwsS3FileSystem $instance;

    private S3Client $client;
    private AwsS3FileSystemConfig $config;

    public function __construct(AwsS3FileSystemConfig $config) {
        $this->config = $config;
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->config->AwsRegion,
            'credentials' => [
                'key' => $this->config->AwsAccessKeyId,
                'secret' => $this->config->AwsSecretAccessKey,
            ],
        ]);
    }

    public static function getInstance(?AwsS3FileSystemConfig $config): AwsS3FileSystem {
        if (!isset(self::$instance)) {
            if (
                empty($config->AwsAccessKeyId) ||
                empty($config->AwsSecretAccessKey) ||
                empty($config->AwsBucket) ||
                empty($config->AwsRegion)
            ) {
                throw new RuntimeException('Configurações do bucket não inicializadas corretamente.');
            }

            self::$instance = new AwsS3FileSystem($config);
        }

        return self::$instance;
    }

    /**
     * @throws Exception
     */
    public function saveFile(string $entityPath, File $file): bool {
        $data = base64_decode($file->data ?? '', true);

        if ($data === false) {
            throw new Exception('Dados do arquivo são inválidos (base64).');
        }

        $key = $this->buildKey($entityPath, $file);

        try {
            $this->client->putObject([
                'Bucket' => $this->config->AwsBucket,
                'Key' => $key,
                'Body' => $data,
                'ACL' => 'private',
                'ContentType' => $file->getContentTypeByExt()->value ?? 'application/octet-stream',
            ]);

            return true;
        } catch (AwsException $e) {
            throw new Exception('Erro ao enviar arquivo para S3: ' . $e->getAwsErrorMessage());
        }
    }

    private function buildKey(string $entityPath, File $file): string {
        return $entityPath . '/' . $file->name . '.' . $file->ext->value;
    }

    /**
     * @throws Exception
     */
    public function readFile(string $entityPath, File $file): void {
        $key = $this->buildKey($entityPath, $file);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->config->AwsBucket,
                'Key' => $key,
            ]);

            $file->data = base64_encode((string)$result['Body']);
        } catch (AwsException $e) {
            throw new Exception('Erro ao ler arquivo do S3: ' . $e->getAwsErrorMessage());
        }
    }

}