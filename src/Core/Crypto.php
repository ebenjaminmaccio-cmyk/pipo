<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cifrado autenticado (XSalsa20-Poly1305) con libsodium para guardar tokens y contraseñas.
 */
final class Crypto
{
    private static function key(): string
    {
        $key = base64_decode((string) Config::get('app.key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('app.key inválida: debe ser 32 bytes en base64.');
        }
        return $key;
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($plain, $nonce, self::key());
    }

    public static function decrypt(string $cipher): string
    {
        $nonce = substr($cipher, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box   = substr($cipher, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($box, $nonce, self::key());
        if ($plain === false) {
            throw new \RuntimeException('No se pudo descifrar (¿cambió app.key?).');
        }
        return $plain;
    }

    /** @param array<string,mixed> $data */
    public static function encryptJson(array $data): string
    {
        return self::encrypt(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    public static function decryptJson(string $cipher): array
    {
        return json_decode(self::decrypt($cipher), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
