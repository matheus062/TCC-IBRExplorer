<?php

declare(strict_types=1);

use IBRExplorer\Api\IBRExplorerApi;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/error-handler.php';

date_default_timezone_set('America/Sao_Paulo');

try {
    IBRExplorerApi::getInstance()->run();
} catch (Throwable $e) {
    handleUnhandledException($e);
}
