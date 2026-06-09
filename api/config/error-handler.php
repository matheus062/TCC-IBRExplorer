<?php

declare(strict_types=1);

function logUnhandledError(Throwable $e): void {
    $logDir = __DIR__ . '/../../files/errors/log';

    if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        return;
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = implode(PHP_EOL, [
            "[$timestamp] UNHANDLED " . get_class($e),
            "Message : " . $e->getMessage(),
            "Code    : " . $e->getCode(),
            "File    : " . $e->getFile() . ':' . $e->getLine(),
            "Trace   :",
            $e->getTraceAsString(),
            str_repeat('-', 80),
        ]) . PHP_EOL;

    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function handleUnhandledException(Throwable $e): void {
    http_response_code(500);
    logUnhandledError($e);

    if (defined('DEBUG') && DEBUG) {
        echo $e->getMessage() . PHP_EOL;
        echo $e->getCode() . PHP_EOL;
        echo $e->getFile() . PHP_EOL;
        echo $e->getLine() . PHP_EOL . PHP_EOL;
        echo $e->getTraceAsString();
    }
}

set_exception_handler('handleUnhandledException');

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        handleUnhandledException(
            new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
        );
    }
});
