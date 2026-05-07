<?php

declare(strict_types=1);

use IBRExplorer\Bootstrap\ApplicationBootstrap;
use IBRExplorer\Worker\PcapWorker;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('America/Sao_Paulo');

$options = getopt('', ['once', 'sleep::']);
$sleepSeconds = max(1, (int)($options['sleep'] ?? PCAP_WORKER_POLL_SECONDS));
$runOnce = array_key_exists('once', $options);

ApplicationBootstrap::boot();

$worker = new PcapWorker($sleepSeconds, $runOnce);

try {
    $worker->run();
} catch (Exception $e) {
    $timestamp = (new DateTime())->format('Y-m-d H:i:s');
    fwrite(STDERR, '[' . $timestamp . '] Erro no worker: ' . $e->getMessage() . PHP_EOL);

    exit(1);
}
