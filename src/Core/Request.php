<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function ipBinary(): string
    {
        return @inet_pton(self::ip()) ?: "\0\0\0\0";
    }

    public static function str(string $key, string $default = '', int $max = 10000): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (!is_string($v)) {
            return $default;
        }
        return mb_substr(trim($v), 0, $max);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    /** @return array<string,mixed> */
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw) > 2_000_000) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
