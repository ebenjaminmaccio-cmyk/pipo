<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Audit, Database};

/**
 * Tareas programadas. Configurar en el hosting para que corra cada 5 minutos:
 *   php /home/usuario/pipo/bin/cron.php
 */
final class CronService
{
    /** @return array<string,int> */
    public static function run(): array
    {
        $stats = ['reminders' => 0, 'event_alerts' => 0, 'synced' => 0, 'digests' => 0];

        // 1) Recordatorios de tareas
        foreach (Database::all("SELECT t.*, u.email, u.name, u.timezone FROM tasks t JOIN users u ON u.id = t.user_id
                                WHERE t.status = 'pendiente' AND t.remind_at IS NOT NULL AND t.remind_at <= UTC_TIMESTAMP() AND t.reminded_at IS NULL
                                AND u.is_active = 1 LIMIT 200") as $t) {
            Database::run('UPDATE tasks SET reminded_at = UTC_TIMESTAMP() WHERE id = ?', [$t['id']]);
            try {
                $due = $t['due_at'] ? "\nVence: " . Time::toLocal($t['due_at'], $t['timezone'], 'd/m/Y H:i') : '';
                Notifier::email($t['email'], '⏰ Recordatorio: ' . $t['title'], "Hola {$t['name']},\n\nTe recuerdo: {$t['title']}{$due}\n\n" . ($t['notes'] ?? '') . "\n\n— Pipo");
                $stats['reminders']++;
            } catch (\Throwable $e) {
                Audit::log('reminder_error', ['task' => $t['id'], 'error' => $e->getMessage()], (int) $t['user_id']);
            }
        }

        // 2) Avisos de reuniones próximas (agenda local)
        foreach (Database::all('SELECT e.*, u.email, u.name, u.timezone FROM events e JOIN users u ON u.id = e.user_id
                                WHERE e.reminded_at IS NULL AND e.remind_minutes IS NOT NULL AND e.remind_minutes > 0
                                AND e.starts_at > UTC_TIMESTAMP() AND e.starts_at <= UTC_TIMESTAMP() + INTERVAL e.remind_minutes MINUTE
                                AND u.is_active = 1 LIMIT 200') as $e) {
            Database::run('UPDATE events SET reminded_at = UTC_TIMESTAMP() WHERE id = ?', [$e['id']]);
            try {
                $when = Time::toLocal($e['starts_at'], $e['timezone'], 'H:i');
                Notifier::email($e['email'], "📅 A las $when: {$e['title']}", "Hola {$e['name']},\n\nTenés \"{$e['title']}\" a las $when." . ($e['location'] ? "\nLugar: {$e['location']}" : '') . "\n\n— Pipo");
                $stats['event_alerts']++;
            } catch (\Throwable $ex) {
                Audit::log('event_alert_error', ['event' => $e['id'], 'error' => $ex->getMessage()], (int) $e['user_id']);
            }
        }

        // 3) Sincronizar mails y 4) resumen diario
        foreach (Database::all('SELECT * FROM users WHERE is_active = 1') as $u) {
            if (Database::one("SELECT id FROM integrations WHERE user_id = ? AND provider IN ('google','imap') LIMIT 1", [$u['id']])) {
                $stats['synced'] += MailService::syncAll((int) $u['id']);
            }
            $local = new \DateTimeImmutable('now', new \DateTimeZone($u['timezone']));
            if ((int) $local->format('G') >= (int) $u['digest_hour']
                && !Database::one('SELECT id FROM digests WHERE user_id = ? AND digest_date = ?', [$u['id'], $local->format('Y-m-d')])) {
                try {
                    $text = DigestService::generate($u, false);
                    Notifier::email($u['email'], '☀️ Tu resumen del ' . $local->format('d/m'), $text . "\n\n— Pipo");
                    $stats['digests']++;
                } catch (\Throwable $e) {
                    Audit::log('digest_error', ['error' => $e->getMessage()], (int) $u['id']);
                }
            }
        }

        // Limpieza
        Database::run('DELETE FROM login_attempts WHERE created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
        return $stats;
    }
}
