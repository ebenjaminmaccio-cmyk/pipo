<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Database, Session, View};
use App\Services\{CalendarService, DigestService, TaskService, Time};

final class DashboardController
{
    public static function index(): void
    {
        $u = Auth::require();
        $uid = (int) $u['id'];
        $today = new \DateTimeImmutable('today', new \DateTimeZone($u['timezone']));
        $from = $today->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $to = $today->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        View::render('dashboard', [
            'title'   => 'Inicio',
            'digest'  => Database::one('SELECT * FROM digests WHERE user_id = ? ORDER BY digest_date DESC LIMIT 1', [$uid]),
            'events'  => CalendarService::list($uid, $from, $to),
            'tasks'   => TaskService::list($uid, 'pendiente', 8),
            'unread'  => (int) (Database::one('SELECT COUNT(*) c FROM emails WHERE user_id = ? AND is_read = 0 AND received_at > UTC_TIMESTAMP() - INTERVAL 7 DAY', [$uid])['c'] ?? 0),
            'drafts'  => (int) (Database::one("SELECT COUNT(*) c FROM email_drafts WHERE user_id = ? AND status = 'pendiente'", [$uid])['c'] ?? 0),
            'wa24'    => (int) (Database::one('SELECT COUNT(*) c FROM whatsapp_messages WHERE user_id = ? AND sent_at > UTC_TIMESTAMP() - INTERVAL 1 DAY', [$uid])['c'] ?? 0),
            'hasAccounts' => (bool) Database::one('SELECT id FROM integrations WHERE user_id = ? LIMIT 1', [$uid]),
        ]);
    }

    public static function digest(): void
    {
        $u = Auth::require();
        Csrf::verify();
        try {
            DigestService::generate($u);
            Session::flash('ok', 'Resumen actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/');
    }
}
