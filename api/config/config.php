<?php

declare(strict_types=1);

$localConfig = __DIR__ . '/local-configuration.php';

if (file_exists($localConfig)) {
    require_once $localConfig;
}

define("DEBUG", (bool)getenv('DEBUG'));

define("POSTGRES_HOST", getenv('POSTGRES_HOST'));
define("POSTGRES_PORT", (int)getenv('POSTGRES_PORT'));
define("POSTGRES_USER", getenv('POSTGRES_USER'));
define("POSTGRES_PASSWORD", getenv('POSTGRES_PASSWORD'));
define("POSTGRES_DATABASE", getenv('POSTGRES_DATABASE'));

define("TOKEN_KEY", getenv('TOKEN_KEY'));
define("TOKEN_ISSUER", getenv('TOKEN_ISSUER'));
define("PASSWORD_PEPPER", getenv('PASSWORD_PEPPER'));

define("APP_EMAIL_URL", getenv('APP_EMAIL_URL'));

define("SMTP_HOST", getenv('SMTP_HOST'));
define("SMTP_USER", getenv('SMTP_USER'));
define("SMTP_PASSWORD", getenv('SMTP_PASSWORD'));

define("AWS_ACCESS_KEY", getenv('AWS_ACCESS_KEY'));
define("AWS_SECRET_KEY", getenv('AWS_SECRET_KEY'));
define("AWS_REGION", getenv('AWS_REGION'));
define("AWS_BUCKET", getenv('AWS_BUCKET'));

define("PCAP_WORKER_CHILDREN", max(1, (int)(getenv('PCAP_WORKER_CHILDREN') ?: 1)));
define("PCAP_WORKER_USER_ID", max(1, (int)(getenv('PCAP_WORKER_USER_ID') ?: 1)));
define("PCAP_WORKER_POLL_SECONDS", max(1, (int)(getenv('PCAP_WORKER_POLL_SECONDS') ?: 30)));
define("PCAP_PACKET_BATCH_SIZE", max(1, (int)(getenv('PCAP_PACKET_BATCH_SIZE') ?: 500)));
define("PCAP_TSHARK_BIN", getenv('PCAP_TSHARK_BIN') ?: 'tshark');
define("PCAP_TSHARK_TIMEOUT_SECONDS", max(1, (int)(getenv('PCAP_TSHARK_TIMEOUT_SECONDS') ?: 30)));
define("PCAP_WORKER_MAX_PROCESS_SECONDS", max(1, (int)(getenv('PCAP_WORKER_MAX_PROCESS_SECONDS') ?: 900)));
define("PCAP_WORKER_STALL_MINUTES", max(1, (int)(getenv('PCAP_WORKER_STALL_MINUTES') ?: 60)));
