<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Audit, Auth, Crypto, Csrf, Request, Session, View};
use App\Services\{GoogleClient, Integrations};

final class GoogleController
{
    public static function connect(): void
    {
        Auth::require();
        Csrf::verify();
        if (!GoogleClient::isConfigured()) {
            Session::flash('error', 'Falta configurar google.client_id y google.client_secret en config.php.');
            View::redirect('/configuracion');
        }
        $state = Crypto::token(24);
        Session::set('google_state', $state);
        View::redirect(GoogleClient::authUrl($state));
    }

    public static function callback(): void
    {
        $u = Auth::require();
        $state = Session::get('google_state');
        Session::forget('google_state');
        if (!is_string($state) || !hash_equals($state, Request::str('state', '', 100))) {
            Session::flash('error', 'La autorización de Google no es válida. Probá de nuevo.');
            View::redirect('/configuracion');
        }
        if (Request::str('error', '', 100) !== '' || Request::str('code', '', 2000) === '') {
            Session::flash('error', 'Cancelaste la conexión con Google.');
            View::redirect('/configuracion');
        }
        try {
            $tokens = GoogleClient::exchangeCode(Request::str('code', '', 2000));
            $email = GoogleClient::emailFromIdToken((string) ($tokens['id_token'] ?? ''));
            unset($tokens['id_token']);
            Integrations::save((int) $u['id'], 'google', $email, $tokens, ['scopes' => $tokens['scope'] ?? '']);
            Audit::log('google_connected', ['account' => $email]);
            Session::flash('ok', "Google conectado ($email): Gmail y Calendar listos.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/configuracion');
    }
}
