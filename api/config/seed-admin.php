<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

$adminEmail = getenv('ADMIN_EMAIL') ?: null;
$adminPassword = getenv('ADMIN_PASSWORD') ?: null;

if (!$adminEmail || !$adminPassword) {
    echo "ADMIN_EMAIL ou ADMIN_PASSWORD não definidos, seed do administrador ignorado.\n";
    exit(0);
}

$dsn = 'pgsql:host=' . POSTGRES_HOST . ';port=' . POSTGRES_PORT . ';dbname=' . POSTGRES_DATABASE;
$pdo = new PDO($dsn, POSTGRES_USER, POSTGRES_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$stmt = $pdo->prepare('
    SELECT u."id", u."password"
    FROM "user" u
    INNER JOIN "user_role" r ON r."user" = u."id"
    WHERE r."type" = 2
    LIMIT 1
');
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    echo "Usuário administrador não encontrado, seed ignorado.\n";
    exit(0);
}

if ($admin['password'] !== null) {
    echo "Administrador já possui senha definida, seed ignorado.\n";
    exit(0);
}

$peppered = hash_hmac('sha1', $adminPassword, PASSWORD_PEPPER);
$hash = password_hash($peppered, PASSWORD_DEFAULT);

$update = $pdo->prepare('UPDATE "user" SET "email" = :email, "password" = :password WHERE "id" = :id');
$update->execute([':email' => $adminEmail, ':password' => $hash, ':id' => $admin['id']]);

echo "Senha do administrador definida com sucesso.\n";
