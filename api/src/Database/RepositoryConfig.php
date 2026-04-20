<?php

declare(strict_types=1);

namespace IBRExplorer\Database;

readonly class RepositoryConfig {

    public string $host;
    public int $port;
    public string $user;
    public string $password;
    public string $database;
    public string $structurePath;
    public string $filesPath;

    public function __construct(
        string $host,
        int    $port,
        string $user,
        string $password,
        string $database,
        string $structurePath = '',
        string $filesPath = ''
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->structurePath = $structurePath;
        $this->filesPath = $filesPath;
    }

}