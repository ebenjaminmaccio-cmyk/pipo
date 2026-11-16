<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use Webklex\PHPIMAP\ClientManager;

/**
 * Mail unificado: Gmail (API oficial) y cualquier casilla IMAP/SMTP.
 * Los mails se cachean en la tabla `emails` para que el asistente pueda buscarlos y resumirlos rápido.
 */
final class MailService
{
    /** Sincroniza los últimos mails de todas las cuentas del usuario. */
    public static function syncAll(int $userId, int $max = 30): int
    {
        $n = 0;
        foreach (Database::all("SELECT * FROM integrations WHERE user_id = ? AND provider IN ('google','imap')", [$userId]) as $acc) {
            try {
                $n += $acc['provider'] === 'google' ? self::syncGmail($acc, $max) : self::syncImap($acc, $max);
            } catch (\Throwable $e) {
                Audit::log('mail_sync_error', ['account' => $acc['label'], 'error' => $e->getMessage()], $userId);
            }
        }
        return $n;
    }

    private static function syncGmail(array $acc, int $max): int
    {
        $list = GoogleClient::api($acc, 'GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages?' . http_build_query([
            'maxResults' => $max, 'q' => 'in:inbox newer_than:7d',
        ]));
        $n = 0;
        foreach ($list['messages'] ?? [] as $m) {
            $exists = Database::one('SELECT id FROM emails WHERE integration_id = ? AND remote_id = ?', [$acc['id'], $m['id']]);
            if ($exists) {
                continue;
            }
            $full = GoogleClient::api($acc, 'GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($m['id']) . '?format=full');
            $headers = [];
            foreach ($full['payload']['headers'] ?? [] as $h) {
                $headers[strtolower($h['name'])] = $h['value'];
            }
            [$fromName, $fromEmail] = self::parseAddress($headers['from'] ?? '');
            self::store($acc, [
                'remote_id'      => $m['id'],
                'thread_id'      => $full['threadId'] ?? null,
                'message_id_hdr' => $headers['message-id'] ?? null,
                'from_name'      => $fromName,
                'from_email'     => $fromEmail,
                'to_email'       => $headers['to'] ?? null,
                'subject'        => $headers['subject'] ?? '(sin asunto)',
                'snippet'        => html_entity_decode((string) ($full['snippet'] ?? ''), ENT_QUOTES),
                'body_text'      => self::gmailBody($full['payload'] ?? []),
                'received_at'    => gmdate('Y-m-d H:i:s', (int) (($full['internalDate'] ?? 0) / 1000)),
                'is_read'        => in_array('UNREAD', $full['labelIds'] ?? [], true) ? 0 : 1,
            ]);
            $n++;
        }
        return $n;
    }

    private static function gmailBody(array $part): string
    {
        $mime = $part['mimeType'] ?? '';
        if ($mime === 'text/plain' && isset($part['body']['data'])) {
            return self::b64url((string) $part['body']['data']);
        }
        $html = null;
        foreach ($part['parts'] ?? [] as $p) {
            $t = self::gmailBody($p);
            if ($t !== '' && ($p['mimeType'] ?? '') !== 'text/html') {
                return $t;
            }
            if (($p['mimeType'] ?? '') === 'text/html' && isset($p['body']['data'])) {
                $html = self::b64url((string) $p['body']['data']);
            }
        }
        if ($mime === 'text/html' && isset($part['body']['data'])) {
            $html = self::b64url((string) $part['body']['data']);
        }
        return $html !== null ? self::htmlToText($html) : '';
    }

    private static function syncImap(array $acc, int $max): int
    {
        $s = Integrations::secret($acc);
        $client = (new ClientManager())->make([
            'host' => $s['imap_host'], 'port' => (int) $s['imap_port'], 'encryption' => $s['imap_encryption'] ?: 'ssl',
            'validate_cert' => true, 'username' => $s['username'], 'password' => $s['password'], 'protocol' => 'imap',
        ]);
        $client->connect();
        $folder = $client->getFolder('INBOX');
        $messages = $folder->messages()->since((new \DateTime('-7 days')))->limit($max)->setFetchOrder('desc')->get();
        $n = 0;
        foreach ($messages as $msg) {
            $uid = (string) $msg->getUid();
            if (Database::one('SELECT id FROM emails WHERE integration_id = ? AND remote_id = ?', [$acc['id'], $uid])) {
                continue;
            }
            $from = $msg->getFrom()[0] ?? null;
            $body = $msg->hasTextBody() ? $msg->getTextBody() : self::htmlToText((string) $msg->getHTMLBody());
            $date = $msg->getDate()->toDate();
            self::store($acc, [
                'remote_id'      => $uid,
                'thread_id'      => null,
                'message_id_hdr' => (string) $msg->getMessageId(),
                'from_name'      => $from?->personal ?? null,
                'from_email'     => $from?->mail ?? null,
                'to_email'       => (string) $msg->getTo(),
                'subject'        => (string) $msg->getSubject() ?: '(sin asunto)',
                'snippet'        => mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 200),
                'body_text'      => $body,
                'received_at'    => $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'is_read'        => $msg->getFlags()->has('seen') ? 1 : 0,
            ]);
            $n++;
        }
        $client->disconnect();
        return $n;
    }

    /** @param array<string,mixed> $data */
    private static function store(array $acc, array $data): void
    {
        $data['body_text'] = mb_substr((string) $data['body_text'], 0, 60000);
        $data['subject'] = mb_substr((string) $data['subject'], 0, 900);
        Database::insert('emails', $data + ['user_id' => $acc['user_id'], 'integration_id' => $acc['id']]);
    }

    /**
     * Envía un borrador APROBADO por el usuario. Nunca se llama desde la IA directamente.
     */
    public static function sendDraft(int $userId, int $draftId): void
    {
        $d = Database::one("SELECT * FROM email_drafts WHERE id = ? AND user_id = ? AND status = 'pendiente'", [$draftId, $userId]);
        if (!$d) {
            throw new \RuntimeException('Borrador no encontrado.');
        }
        $acc = Integrations::get($userId, (int) $d['integration_id']);
        if (!$acc) {
            throw new \RuntimeException('La cuenta de mail ya no está conectada.');
        }
        $orig = $d['email_id'] ? Database::one('SELECT * FROM emails WHERE id = ? AND user_id = ?', [$d['email_id'], $userId]) : null;

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'quoted-printable';
        $mail->setFrom($acc['provider'] === 'google' ? $acc['label'] : Integrations::secret($acc)['username']);
        foreach (preg_split('/[,;]\s*/', (string) $d['to_email']) ?: [] as $to) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($to);
            }
        }
        $mail->Subject = (string) $d['subject'];
        $mail->Body = (string) $d['body_text'];
        if ($orig && $orig['message_id_hdr']) {
            $mail->addCustomHeader('In-Reply-To', $orig['message_id_hdr']);
            $mail->addCustomHeader('References', $orig['message_id_hdr']);
        }

        try {
            if ($acc['provider'] === 'google') {
                $mail->preSend();
                $raw = rtrim(strtr(base64_encode($mail->getSentMIMEMessage()), '+/', '-_'), '=');
                $payload = ['raw' => $raw];
                if ($orig && $orig['thread_id']) {
                    $payload['threadId'] = $orig['thread_id'];
                }
                GoogleClient::api($acc, 'POST', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', $payload);
            } else {
                $s = Integrations::secret($acc);
                $mail->isSMTP();
                $mail->Host = $s['smtp_host'];
                $mail->Port = (int) $s['smtp_port'];
                $mail->SMTPAuth = true;
                $mail->Username = $s['username'];
                $mail->Password = $s['password'];
                $mail->SMTPSecure = ($s['smtp_encryption'] ?? 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->send();
            }
            Database::run("UPDATE email_drafts SET status = 'enviado', sent_at = UTC_TIMESTAMP() WHERE id = ?", [$draftId]);
            Audit::log('email_sent', ['draft' => $draftId, 'to' => $d['to_email']], $userId);
        } catch (\Throwable $e) {
            Database::run("UPDATE email_drafts SET status = 'error', error = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 1000), $draftId]);
            throw new \RuntimeException('No se pudo enviar: ' . $e->getMessage());
        }
    }

    /** @return array{0:?string,1:?string} */
    public static function parseAddress(string $raw): array
    {
        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $raw, $m)) {
            return [trim($m[1]) ?: null, trim($m[2])];
        }
        return [null, trim($raw) ?: null];
    }

    private static function b64url(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', $html) ?? '';
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</tr>#i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? '') ?? '');
    }
}
