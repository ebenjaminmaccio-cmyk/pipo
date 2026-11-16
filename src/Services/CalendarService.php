<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Agenda: se guarda siempre en la base local y, si hay cuenta de Google conectada,
 * también se crea/lee en Google Calendar.
 */
final class CalendarService
{
    /** @return list<array<string,mixed>> eventos entre dos fechas UTC */
    public static function list(int $userId, string $fromUtc, string $toUtc): array
    {
        $events = [];
        $google = GoogleClient::primary($userId);
        $localGoogleIds = [];

        foreach (Database::all('SELECT * FROM events WHERE user_id = ? AND starts_at < ? AND ends_at > ? ORDER BY starts_at', [$userId, $toUtc, $fromUtc]) as $e) {
            $e['source'] = 'local';
            $events[] = $e;
            if ($e['google_id']) {
                $localGoogleIds[$e['google_id']] = true;
            }
        }

        if ($google) {
            try {
                $r = GoogleClient::api($google, 'GET', 'https://www.googleapis.com/calendar/v3/calendars/primary/events?' . http_build_query([
                    'timeMin' => self::iso($fromUtc), 'timeMax' => self::iso($toUtc),
                    'singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 100,
                ]));
                foreach ($r['items'] ?? [] as $g) {
                    if (isset($localGoogleIds[$g['id']]) || ($g['status'] ?? '') === 'cancelled') {
                        continue;
                    }
                    $start = $g['start']['dateTime'] ?? (($g['start']['date'] ?? '') . 'T00:00:00Z');
                    $end = $g['end']['dateTime'] ?? (($g['end']['date'] ?? '') . 'T00:00:00Z');
                    $events[] = [
                        'id' => null, 'google_id' => $g['id'], 'source' => 'google',
                        'title' => $g['summary'] ?? '(sin título)', 'description' => $g['description'] ?? null,
                        'location' => $g['location'] ?? null,
                        'attendees' => json_encode(array_column($g['attendees'] ?? [], 'email')),
                        'starts_at' => self::toUtc($start), 'ends_at' => self::toUtc($end),
                    ];
                }
            } catch (\Throwable) {
                // si Google falla, mostramos al menos la agenda local
            }
        }
        usort($events, fn ($a, $b) => strcmp((string) $a['starts_at'], (string) $b['starts_at']));
        return $events;
    }

    /**
     * @param list<string> $attendees
     * @return array<string,mixed>
     */
    public static function create(int $userId, string $title, string $startUtc, string $endUtc, ?string $description = null, ?string $location = null, array $attendees = [], int $remindMinutes = 30): array
    {
        $attendees = array_values(array_filter($attendees, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        $googleId = null;
        $google = GoogleClient::primary($userId);
        if ($google) {
            $body = [
                'summary' => $title, 'description' => $description, 'location' => $location,
                'start' => ['dateTime' => self::iso($startUtc)], 'end' => ['dateTime' => self::iso($endUtc)],
                'attendees' => array_map(fn ($a) => ['email' => $a], $attendees),
                'reminders' => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => $remindMinutes]]],
            ];
            $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events' . ($attendees ? '?sendUpdates=all' : '');
            $g = GoogleClient::api($google, 'POST', $url, $body);
            $googleId = $g['id'] ?? null;
        }
        $id = Database::insert('events', [
            'user_id' => $userId, 'title' => mb_substr($title, 0, 255), 'description' => $description,
            'location' => $location, 'attendees' => json_encode($attendees), 'starts_at' => $startUtc,
            'ends_at' => $endUtc, 'google_id' => $googleId, 'remind_minutes' => $remindMinutes,
        ]);
        return ['id' => $id, 'google_id' => $googleId, 'synced_google' => $googleId !== null];
    }

    public static function delete(int $userId, int $id): void
    {
        $e = Database::one('SELECT * FROM events WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$e) {
            return;
        }
        if ($e['google_id'] && ($google = GoogleClient::primary($userId))) {
            try {
                GoogleClient::api($google, 'DELETE', 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($e['google_id']));
            } catch (\Throwable) {
            }
        }
        Database::run('DELETE FROM events WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    private static function iso(string $utc): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private static function toUtc(string $iso): string
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
