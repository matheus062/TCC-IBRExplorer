<?php

declare(strict_types=1);

use IBRExplorer\Api\IBRExplorerApi;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('America/Sao_Paulo');

try {
    IBRExplorerApi::getInstance()->run();
} catch (Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    echo $e->getCode() . PHP_EOL;
    echo $e->getFile() . PHP_EOL;
    echo $e->getLine() . PHP_EOL . PHP_EOL;


    echo $e->getTraceAsString();
}
