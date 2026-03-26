<?php

declare(strict_types=1);

use IBRExplorer\Database\RepositoryConfig;
use IBRExplorer\Database\Structure\Structure;

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

$repositoryConfig = new RepositoryConfig(
    MYSQL_HOST,
    MYSQL_PORT,
    MYSQL_USER,
    MYSQL_PASSWORD,
    MYSQL_DATABASE,
    __DIR__ . '/../src/Database/Structure/Database/',
    __DIR__ . '/../files/'
);

try {
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }

    (new Structure($repositoryConfig))->updateStart();

    die('Atualização bem sucedida.');
} catch (Exception $e) {
    echo 'Message: ' . $e->getMessage() . PHP_EOL;
    echo 'Code: ' . $e->getCode() . PHP_EOL;
    echo 'File: ' . $e->getFile() . PHP_EOL;
    echo 'Line: ' . $e->getLine() . PHP_EOL;

    die('Ocorreu um erro ao atualizar a base de dados.');
}
