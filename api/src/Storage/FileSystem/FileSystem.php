<?php

declare(strict_types=1);

namespace IBRExplorer\Storage\FileSystem;

use Exception;
use IBRExplorer\Util\File;
use RuntimeException;

class FileSystem {

    private static ?FileSystem $instance;

    private string $filesPath;
    private string $assetsPath;

    public function __construct(string $filesPath, string $assetsPath) {
        $this->filesPath = $filesPath;
        $this->assetsPath = $assetsPath;
    }

    public static function getInstance(?string $filesPath = null, ?string $assetsPath = null): FileSystem {
        if (!isset(self::$instance)) {
            if (empty($filesPath) || empty($assetsPath)) {
                throw new RuntimeException('Sistema de arquivos não inicializado.');
            }

            self::$instance = new FileSystem($filesPath, $assetsPath);
        }

        return self::$instance;
    }

    public function getAssetsPath(): string {
        return $this->assetsPath;
    }

    /**
     * @throws Exception
     */
    public function saveFile(string $entityPath, File $file): bool {
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
        $fullPath = $this->getFilesPath() . $entityPath . DIRECTORY_SEPARATOR . $file->name . '.' . $file->ext->value;

        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            throw new Exception('Arquivo não localizado ou sem permissão de leitura.');
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new Exception('Arquivo não localizado ou sem permissão de leitura.');
        }

        $file->data = base64_encode($contents);
    }

}