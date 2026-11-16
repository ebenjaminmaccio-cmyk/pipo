<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Audit, Auth, Config, Crypto, Csrf, Database, Request, Session, Totp, View};
use App\Services\{GoogleClient, Integrations, LimeCrmService};
use Webklex\PHPIMAP\ClientManager;

final class SettingsController
{
    public static function index(): void
    {
        $u = Auth::require();
        $uid = (int) $u['id'];
        $pendingSecret = Session::get('totp_setup');
        View::render('settings', [
            'title' => 'Configuración',
            'integrations' => Integrations::forUser($uid),
            'googleReady' => GoogleClient::isConfigured(),
            'memories' => Database::all('SELECT * FROM memories WHERE user_id = ? ORDER BY id DESC', [$uid]),
            'usage' => Database::one('SELECT COALESCE(SUM(input_tokens),0) inp, COALESCE(SUM(output_tokens),0) outp, COUNT(*) calls FROM ai_usage WHERE user_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY', [$uid]),
            'totpSecret' => is_string($pendingSecret) ? $pendingSecret : null,
            'totpUri' => is_string($pendingSecret) ? Totp::uri($pendingSecret, $u['email'], (string) Config::get('app.name', 'Pipo')) : null,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public static function profile(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $tz = Request::str('timezone', $u['timezone'], 64);
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = $u['timezone'];
        }
        Database::run('UPDATE users SET name = ?, timezone = ?, digest_hour = ? WHERE id = ?', [
            Request::str('name', $u['name'], 120) ?: $u['name'], $tz, max(0, min(23, Request::int('digest_hour', 8))), $u['id'],
        ]);
        $new = Request::str('new_password', '', 500);
        if ($new !== '') {
            if (!password_verify(Request::str('current_password', '', 500), $u['password_hash'])) {
                Session::flash('error', 'La contraseña actual no es correcta.');
                View::redirect('/configuracion');
            }
            if (mb_strlen($new) < 12) {
                Session::flash('error', 'La nueva contraseña debe tener al menos 12 caracteres.');
                View::redirect('/configuracion');
            }
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $u['id']]);
            Audit::log('password_changed');
        }
        Session::flash('ok', 'Perfil actualizado.');
        View::redirect('/configuracion');
    }

    public static function imap(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $secret = [
            'imap_host' => Request::str('imap_host', '', 190), 'imap_port' => Request::int('imap_port', 993),
            'imap_encryption' => Request::str('imap_encryption', 'ssl', 5) === 'tls' ? 'tls' : 'ssl',
            'smtp_host' => Request::str('smtp_host', '', 190), 'smtp_port' => Request::int('smtp_port', 465),
            'smtp_encryption' => Request::str('smtp_encryption', 'ssl', 5) === 'tls' ? 'tls' : 'ssl',
            'username' => Request::str('username', '', 190), 'password' => Request::str('password', '', 500),
        ];
        if (!filter_var($secret['username'], FILTER_VALIDATE_EMAIL) || $secret['imap_host'] === '' || $secret['smtp_host'] === '' || $secret['password'] === '') {
            Session::flash('error', 'Completá todos los datos de la casilla.');
            View::redirect('/configuracion');
        }
        try {
            $client = (new ClientManager())->make([
                'host' => $secret['imap_host'], 'port' => $secret['imap_port'], 'encryption' => $secret['imap_encryption'],
                'validate_cert' => true, 'username' => $secret['username'], 'password' => $secret['password'], 'protocol' => 'imap',
            ]);
            $client->connect();
            $client->disconnect();
        } catch (\Throwable $e) {
            Session::flash('error', 'No me pude conectar por IMAP: ' . $e->getMessage());
            View::redirect('/configuracion');
        }
        Integrations::save((int) $u['id'], 'imap', $secret['username'], $secret);
        Audit::log('imap_connected', ['account' => $secret['username']]);
        Session::flash('ok', 'Casilla conectada. Tocá "Actualizar" en Mail para descargar los mensajes.');
        View::redirect('/configuracion');
    }

    /** Conecta Lime CRM con la URL y el token de solo lectura generado en Lime. */
    public static function lime(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $url = LimeCrmService::normalizeUrl(Request::str('url', '', 255));
        $token = strtolower(Request::str('token', '', 100));
        if ($url === null || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            Session::flash('error', 'Revisá la dirección de Lime y el token (64 caracteres).');
            View::redirect('/configuracion#lime');
        }
        try {
            LimeCrmService::fetch($url, $token, 0, 1); // prueba de conexión
        } catch (\Throwable $e) {
            Session::flash('error', 'No me pude conectar con Lime: ' . $e->getMessage());
            View::redirect('/configuracion#lime');
        }
        $label = 'Lime CRM (' . parse_url($url, PHP_URL_HOST) . ')';
        $id = Integrations::save((int) $u['id'], 'whatsapp_crm', $label, ['url' => $url, 'token' => $token], ['cursor' => 0]);
        Audit::log('lime_connected', ['url' => $url]);
        @set_time_limit(120);
        try {
            $n = LimeCrmService::sync(Integrations::get((int) $u['id'], $id));
            Session::flash('ok', "Lime CRM conectado. Se trajeron $n mensajes" . ($n >= 10000 ? ' (el resto se completa solo en los próximos minutos).' : '.'));
        } catch (\Throwable $e) {
            Session::flash('error', 'Conectado, pero falló la primera descarga: ' . $e->getMessage());
        }
        View::redirect('/whatsapp');
    }

    public static function deleteIntegration(): void
    {
        $u = Auth::require();
        Csrf::verify();
        Integrations::delete((int) $u['id'], Request::int('id'));
        Audit::log('integration_deleted', ['id' => Request::int('id')]);
        Session::flash('ok', 'Cuenta desconectada.');
        View::redirect('/configuracion');
    }

    public static function totp(): void
    {
        $u = Auth::require();
        Csrf::verify();
        $do = Request::str('do', '', 10);
        if ($do === 'start') {
            Session::set('totp_setup', Totp::generateSecret());
        } elseif ($do === 'confirm') {
            $secret = Session::get('totp_setup');
            if (is_string($secret) && Totp::verify($secret, Request::str('code', '', 10))) {
                Database::run('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?', [Crypto::encrypt($secret), $u['id']]);
                Session::forget('totp_setup');
                Audit::log('2fa_enabled');
                Session::flash('ok', 'Verificación en dos pasos activada.');
            } else {
                Session::flash('error', 'El código no coincide. Probá de nuevo.');
            }
        } elseif ($do === 'disable') {
            if (password_verify(Request::str('password', '', 500), $u['password_hash'])) {
                Database::run('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?', [$u['id']]);
                Audit::log('2fa_disabled');
                Session::flash('ok', 'Verificación en dos pasos desactivada.');
            } else {
                Session::flash('error', 'Contraseña incorrecta.');
            }
        }
        View::redirect('/configuracion#seguridad');
    }

    public static function memory(): void
    {
        $u = Auth::require();
        Csrf::verify();
        if (Request::str('do', '', 10) === 'add' && ($fact = Request::str('fact', '', 1000)) !== '') {
            Database::insert('memories', ['user_id' => $u['id'], 'fact' => $fact]);
        } else {
            Database::run('DELETE FROM memories WHERE id = ? AND user_id = ?', [Request::int('id'), $u['id']]);
        }
        View::redirect('/configuracion#memoria');
    }

    public static function rotateWebhook(): void
    {
        $u = Auth::require();
        Csrf::verify();
        Database::run('UPDATE users SET webhook_token = ? WHERE id = ?', [Crypto::token(32), $u['id']]);
        Audit::log('webhook_rotated');
        Session::flash('ok', 'Se generó una nueva URL de webhook. Actualizala en tu CRM.');
        View::redirect('/whatsapp');
    }
}
