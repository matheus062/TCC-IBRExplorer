<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

use RuntimeException;

class EncryptionService {

    private const string CIPHER = 'aes-256-gcm';
    private const int    IV_LENGTH = 12;
    private const int    TAG_LENGTH = 16;

    public static function encrypt(string $plaintext): string {
        $key = self::deriveKey();
        /** @noinspection PhpUnhandledExceptionInspection */
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao criptografar dados de integração.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function deriveKey(): string {
        $raw = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : null;

        if (empty($raw)) {
            throw new RuntimeException('ENCRYPTION_KEY não configurada.');
        }

        return hash('sha256', (string)$raw, true);
    }

    public static function decrypt(string $encrypted): string {
        $key = self::deriveKey();
        $raw = base64_decode($encrypted, true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Dados criptografados inválidos ou corrompidos.');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Falha ao descriptografar dados. Verifique ENCRYPTION_KEY.');
        }

        return $plaintext;
    }

}
