<?php

declare(strict_types=1);

namespace IBRExplorer\Bootstrap;

use Exception;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Database\RepositoryConfig;
use IBRExplorer\Storage\AwsS3\Config\AwsS3FileSystemConfig;
use IBRExplorer\Storage\FileSystem;

class ApplicationBootstrap {

    private static bool $booted = false;


    public static function boot(): void {
        if (self::$booted) {
            return;
        }

        self::startFileSystem();
        /** @noinspection PhpUnhandledExceptionInspection */
        self::startDbConnection();
        self::$booted = true;
    }

    private static function startFileSystem(): void {
        FileSystem::getInstance(
            __DIR__ . '/../../files/',
            __DIR__ . '/../../assets/',
            new AwsS3FileSystemConfig(
                AWS_REGION,
                AWS_BUCKET,
                AWS_ACCESS_KEY,
                AWS_SECRET_KEY
            )
        );
    }

    /**
     * @throws Exception
     */
    private static function startDbConnection(): void {
        $repositoryConfig = new RepositoryConfig(
            POSTGRES_HOST,
            POSTGRES_PORT,
            POSTGRES_USER,
            POSTGRES_PASSWORD,
            POSTGRES_DATABASE,
            __DIR__ . '/../Database/Structure/Database/',
            __DIR__ . '/../../files/'
        );
        $database = new PostgreSQL($repositoryConfig);
        $database->initDatabase();
        $database->initUser(PCAP_WORKER_USER_ID);
    }

}
