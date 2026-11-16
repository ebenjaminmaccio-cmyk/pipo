<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Códigos de 6 dígitos (RFC 6238) compatibles con Google Authenticator, Authy, 1Password...
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, ?int $counter = null): string
    {
        $counter ??= intdiv(time(), 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $num = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($num % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        $now = intdiv(time(), 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::code($secret, $now + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $email, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }
}
