<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /** @param array<string,mixed> $detail */
    public static function log(string $action, array $detail = [], ?int $userId = null): void
    {
        try {
            Database::insert('audit_log', [
                'user_id' => $userId ?? (Auth::id() ?: null),
                'action'  => mb_substr($action, 0, 80),
                'detail'  => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'ip'      => PHP_SAPI === 'cli' ? null : Request::ipBinary(),
            ]);
        } catch (\Throwable) {
            // la auditoría nunca debe romper la app
        }
    }
}
