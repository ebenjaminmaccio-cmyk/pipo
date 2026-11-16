<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Request, Session, View};
use App\Services\{TaskService, Time};

final class TaskController
{
    public static function index(): void
    {
        $u = Auth::require();
        View::render('tasks', [
            'title' => 'Tareas',
            'pending' => TaskService::list((int) $u['id'], 'pendiente'),
            'done' => TaskService::list((int) $u['id'], 'hecha', 30),
        ]);
    }

    public static function store(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $title = Request::str('title', '', 255);
        if ($title === '') {
            Session::flash('error', 'La tarea necesita un título.');
            View::redirect('/tareas');
        }
        TaskService::create(
            (int) $u['id'], $title, Request::str('notes', '', 5000) ?: null, Request::str('priority', 'media', 10),
            Time::toUtc(str_replace('T', ' ', Request::str('due_at', '', 20)), $u['timezone']),
            Time::toUtc(str_replace('T', ' ', Request::str('remind_at', '', 20)), $u['timezone'])
        );
        Session::flash('ok', 'Tarea creada.');
        View::redirect('/tareas');
    }

    public static function action(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $id = Request::int('id');
        match (Request::str('do', '', 20)) {
            'complete' => TaskService::complete((int) $u['id'], $id),
            'reopen' => TaskService::reopen((int) $u['id'], $id),
            'delete' => TaskService::delete((int) $u['id'], $id),
            default => null,
        };
        View::redirect('/tareas');
    }
}
