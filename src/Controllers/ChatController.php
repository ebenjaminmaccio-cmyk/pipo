<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Database, Request, View};
use App\Services\Assistant;

final class ChatController
{
    public static function index(): void
    {
        $u = Auth::require();
        $history = array_reverse(Database::all('SELECT role, content, created_at FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$u['id']]));
        View::render('chat', ['title' => 'Asistente', 'history' => $history]);
    }

    public static function send(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $data = Request::json();
        $msg = mb_substr(trim((string) ($data['message'] ?? '')), 0, 8000);
        if ($msg === '') {
            View::json(['error' => 'Mensaje vacío'], 422);
        }
        // límite simple: 30 mensajes por minuto por usuario
        $recent = (int) (Database::one("SELECT COUNT(*) c FROM chat_messages WHERE user_id = ? AND role = 'user' AND created_at > UTC_TIMESTAMP() - INTERVAL 1 MINUTE", [$u['id']])['c'] ?? 0);
        if ($recent >= 30) {
            View::json(['error' => 'Muchos mensajes seguidos. Esperá un minuto.'], 429);
        }
        @set_time_limit(180);
        try {
            $r = (new Assistant($u))->chat($msg);
            View::json($r);
        } catch (\Throwable $e) {
            error_log((string) $e);
            View::json(['error' => 'No pude responder: ' . $e->getMessage()], 500);
        }
    }

    public static function clear(): void
    {
        $u = Auth::require();
        Csrf::verify();
        Database::run('DELETE FROM chat_messages WHERE user_id = ?', [$u['id']]);
        View::redirect('/asistente');
    }
}
