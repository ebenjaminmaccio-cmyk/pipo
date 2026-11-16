<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Audit, Auth, Csrf, Database, Request, Session, View};
use App\Services\{DigestService, MailService};

final class MailController
{
    public static function index(): void
    {
        $u = Auth::require();
        $uid = (int) $u['id'];
        $q = Request::str('q', '', 200);
        $sql = 'SELECT e.id, e.from_name, e.from_email, e.subject, e.snippet, e.received_at, e.is_read, e.ai_priority, i.label account
                FROM emails e JOIN integrations i ON i.id = e.integration_id WHERE e.user_id = ?';
        $p = [$uid];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sql .= ' AND (e.from_name LIKE ? OR e.from_email LIKE ? OR e.subject LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        View::render('mail/index', [
            'title' => 'Mail',
            'q' => $q,
            'emails' => Database::all($sql . ' ORDER BY e.received_at DESC LIMIT 100', $p),
            'drafts' => Database::all("SELECT d.*, i.label account FROM email_drafts d JOIN integrations i ON i.id = d.integration_id WHERE d.user_id = ? AND d.status IN ('pendiente','error') ORDER BY d.id DESC", [$uid]),
            'accounts' => Database::all("SELECT id, label FROM integrations WHERE user_id = ? AND provider IN ('google','imap')", [$uid]),
        ]);
    }

    public static function show(string $id): void
    {
        $u = Auth::require();
        $e = Database::one('SELECT e.*, i.label account FROM emails e JOIN integrations i ON i.id = e.integration_id WHERE e.id = ? AND e.user_id = ?', [(int) $id, $u['id']]);
        if (!$e) {
            http_response_code(404);
            exit('No encontrado');
        }
        Database::run('UPDATE emails SET is_read = 1 WHERE id = ?', [$e['id']]);
        View::render('mail/show', ['title' => $e['subject'], 'email' => $e]);
    }

    public static function sync(): void
    {
        $u = Auth::require();
        Csrf::verify();
        @set_time_limit(120);
        $n = MailService::syncAll((int) $u['id']);
        Session::flash('ok', $n === 0 ? 'No hay mails nuevos.' : "Se descargaron $n mails nuevos.");
        View::redirect('/mail');
    }

    public static function summarize(string $id): void
    {
        $u = Auth::require();
        Csrf::verify();
        try {
            DigestService::summarizeEmail((int) $u['id'], (int) $id);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/mail/' . (int) $id);
    }

    /** Crear borrador manual (o respuesta) */
    public static function compose(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $acc = Database::one("SELECT id FROM integrations WHERE id = ? AND user_id = ? AND provider IN ('google','imap')", [Request::int('account_id'), $u['id']]);
        if (!$acc) {
            Session::flash('error', 'Elegí una cuenta de mail.');
            View::redirect('/mail');
        }
        $replyTo = Request::int('email_id') ?: null;
        if ($replyTo && !Database::one('SELECT id FROM emails WHERE id = ? AND user_id = ?', [$replyTo, $u['id']])) {
            $replyTo = null;
        }
        Database::insert('email_drafts', [
            'user_id' => $u['id'], 'integration_id' => $acc['id'], 'email_id' => $replyTo,
            'to_email' => Request::str('to', '', 900), 'subject' => Request::str('subject', '', 900), 'body_text' => Request::str('body', '', 50000),
        ]);
        Session::flash('ok', 'Borrador guardado. Revisalo y tocá "Enviar".');
        View::redirect('/mail#borradores');
    }

    public static function draftAction(string $id): void
    {
        $u = Auth::require();
        Csrf::verify();
        $uid = (int) $u['id'];
        $draftId = (int) $id;
        $do = Request::str('do', '', 20);

        if (in_array($do, ['save', 'send'], true)) {
            Database::run("UPDATE email_drafts SET to_email = ?, subject = ?, body_text = ?, status = 'pendiente' WHERE id = ? AND user_id = ? AND status IN ('pendiente','error')", [
                Request::str('to', '', 900), Request::str('subject', '', 900), Request::str('body', '', 50000), $draftId, $uid,
            ]);
        }
        try {
            match ($do) {
                'send' => MailService::sendDraft($uid, $draftId),
                'discard' => Database::run("UPDATE email_drafts SET status = 'descartado' WHERE id = ? AND user_id = ?", [$draftId, $uid]),
                default => null,
            };
            Session::flash('ok', match ($do) { 'send' => 'Mail enviado.', 'discard' => 'Borrador descartado.', default => 'Borrador guardado.' });
            if ($do === 'discard') {
                Audit::log('draft_discarded', ['draft' => $draftId], $uid);
            }
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/mail#borradores');
    }
}
