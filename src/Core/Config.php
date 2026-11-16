<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $data = [];

    /** @param array<string,mixed> $data */
    public static function load(array $data): void
    {
        self::$data = $data;
    }

    /** Lee valores con notación de puntos: Config::get('db.host') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
