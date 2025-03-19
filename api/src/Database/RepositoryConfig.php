<?php

declare(strict_types=1);

namespace IBRExplorer\Database;

readonly class RepositoryConfig {

    public string $mysqlHost;
    public int $mysqlPort;
    public string $mysqlUser;
    public string $mysqlPassword;
    public string $mysqlDatabase;
    public string $structurePath;
    public string $filesPath;

    public function __construct(
        string $mysqlHost,
        int    $mysqlPort,
        string $mysqlUser,
        string $mysqlPassword,
        string $mysqlDatabase,
        string $structurePath = '',
        string $filesPath = ''
    ) {
        $this->mysqlHost = $mysqlHost;
        $this->mysqlPort = $mysqlPort;
        $this->mysqlUser = $mysqlUser;
        $this->mysqlPassword = $mysqlPassword;
        $this->mysqlDatabase = $mysqlDatabase;
        $this->structurePath = $structurePath;
        $this->filesPath = $filesPath;
    }

}