<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Request, Session, View};
use App\Services\{CalendarService, Time};

final class CalendarController
{
    public static function index(): void
    {
        $u = Auth::require();
        $tz = new \DateTimeZone($u['timezone']);
        $offset = max(-52, min(52, Request::int('semana')));
        $start = (new \DateTimeImmutable('monday this week', $tz))->modify(($offset >= 0 ? '+' : '') . $offset . ' week');
        $end = $start->modify('+7 days');
        $utc = new \DateTimeZone('UTC');
        $events = CalendarService::list((int) $u['id'], $start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s'));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$start->modify("+$i day")->format('Y-m-d')] = [];
        }
        foreach ($events as $e) {
            $k = Time::toLocal($e['starts_at'], $u['timezone'], 'Y-m-d');
            if (isset($days[$k])) {
                $days[$k][] = $e;
            }
        }
        View::render('calendar', ['title' => 'Agenda', 'days' => $days, 'offset' => $offset, 'start' => $start]);
    }

    public static function store(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $start = Time::toUtc(str_replace('T', ' ', Request::str('starts_at', '', 20)), $u['timezone']);
        $end = Time::toUtc(str_replace('T', ' ', Request::str('ends_at', '', 20)), $u['timezone']);
        $title = Request::str('title', '', 255);
        if ($title === '' || !$start || !$end || $end <= $start) {
            Session::flash('error', 'Revisá el título y las fechas del evento.');
            View::redirect('/agenda');
        }
        $attendees = array_filter(array_map('trim', explode(',', Request::str('attendees', '', 2000))));
        try {
            $r = CalendarService::create((int) $u['id'], $title, $start, $end, Request::str('description', '', 5000) ?: null, Request::str('location', '', 255) ?: null, $attendees, Request::int('remind_minutes', 30));
            Session::flash('ok', $r['synced_google'] ? 'Evento creado y sincronizado con Google Calendar.' : 'Evento creado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/agenda');
    }

    public static function delete(): void
    {
        $u = Auth::require();
        Csrf::verify();
        CalendarService::delete((int) $u['id'], Request::int('id'));
        Session::flash('ok', 'Evento eliminado.');
        View::redirect('/agenda');
    }
}
