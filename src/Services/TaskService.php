<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class TaskService
{
    /** @return list<array<string,mixed>> */
    public static function list(int $userId, string $status = 'pendiente', int $limit = 100): array
    {
        return Database::all(
            "SELECT * FROM tasks WHERE user_id = ? AND status = ? ORDER BY due_at IS NULL, due_at, FIELD(priority,'alta','media','baja'), id DESC LIMIT " . max(1, min($limit, 500)),
            [$userId, $status]
        );
    }

    public static function create(int $userId, string $title, ?string $notes = null, string $priority = 'media', ?string $dueUtc = null, ?string $remindUtc = null): int
    {
        if (!in_array($priority, ['baja', 'media', 'alta'], true)) {
            $priority = 'media';
        }
        return Database::insert('tasks', [
            'user_id' => $userId, 'title' => mb_substr($title, 0, 255), 'notes' => $notes,
            'priority' => $priority, 'due_at' => $dueUtc, 'remind_at' => $remindUtc,
        ]);
    }

    public static function complete(int $userId, int $id): bool
    {
        return Database::run("UPDATE tasks SET status = 'hecha', completed_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?", [$id, $userId])->rowCount() > 0;
    }

    public static function reopen(int $userId, int $id): void
    {
        Database::run("UPDATE tasks SET status = 'pendiente', completed_at = NULL WHERE id = ? AND user_id = ?", [$id, $userId]);
    }

    public static function delete(int $userId, int $id): void
    {
        Database::run('DELETE FROM tasks WHERE id = ? AND user_id = ?', [$id, $userId]);
    }
}
