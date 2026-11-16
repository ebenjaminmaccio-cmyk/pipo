<?php
declare(strict_types=1);

namespace App\Services;

/** Conversión entre la hora local del usuario y UTC (la base guarda todo en UTC). */
final class Time
{
    public static function toUtc(?string $local, string $tz): ?string
    {
        if ($local === null || trim($local) === '') {
            return null;
        }
        try {
            $d = new \DateTimeImmutable($local, new \DateTimeZone($tz));
        } catch (\Throwable) {
            return null;
        }
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function toLocal(?string $utc, string $tz, string $format = 'Y-m-d H:i'): ?string
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format($format);
    }

    public static function nowUtc(string $modify = 'now'): string
    {
        return (new \DateTimeImmutable($modify, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
