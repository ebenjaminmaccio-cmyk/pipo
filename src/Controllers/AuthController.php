<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Audit, Auth, Config, Crypto, Csrf, Database, Request, Session, Totp, View};

final class AuthController
{
    public static function loginForm(): void
    {
        if (Auth::user()) {
            View::redirect('/');
        }
        if ((int) (Database::one('SELECT COUNT(*) c FROM users')['c'] ?? 0) === 0) {
            View::redirect('/instalar');
        }
        View::render('auth/login', ['title' => 'Ingresar'], 'layout_guest');
    }

    public static function login(): void
    {
        Csrf::verify();
        $result = Auth::attempt(Request::str('email', '', 190), Request::str('password', '', 500));
        if (is_string($result)) {
            Session::flash('error', $result);
            View::redirect('/login');
        }
        if ((int) $result['totp_enabled'] === 1) {
            Session::regenerate();
            Session::set('pending_2fa', ['id' => (int) $result['id'], 'at' => time()]);
            View::redirect('/login/2fa');
        }
        Auth::login($result);
        View::redirect('/');
    }

    public static function twoFactorForm(): void
    {
        if (!Session::get('pending_2fa')) {
            View::redirect('/login');
        }
        View::render('auth/two_factor', ['title' => 'Verificación'], 'layout_guest');
    }

    public static function twoFactor(): void
    {
        Csrf::verify();
        $p = Session::get('pending_2fa');
        if (!is_array($p) || time() - $p['at'] > 300) {
            Session::forget('pending_2fa');
            Session::flash('error', 'La verificación expiró. Ingresá de nuevo.');
            View::redirect('/login');
        }
        $user = Database::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$p['id']]);
        $tries = (int) Session::get('2fa_tries', 0);
        if (!$user || $tries >= 5) {
            Session::forget('pending_2fa');
            Session::forget('2fa_tries');
            Session::flash('error', 'Demasiados intentos.');
            View::redirect('/login');
        }
        if (!Totp::verify(Crypto::decrypt((string) $user['totp_secret']), Request::str('code', '', 10))) {
            Session::set('2fa_tries', $tries + 1);
            Session::flash('error', 'Código incorrecto.');
            View::redirect('/login/2fa');
        }
        Session::forget('2fa_tries');
        Auth::login($user);
        View::redirect('/');
    }

    public static function logout(): void
    {
        Csrf::verify();
        Auth::logout();
        View::redirect('/login');
    }

    /** Instalación: crea el primer usuario (admin). Solo funciona mientras no exista ninguno. */
    public static function setupForm(): void
    {
        if ((int) (Database::one('SELECT COUNT(*) c FROM users')['c'] ?? 0) > 0) {
            View::redirect('/login');
        }
        View::render('auth/setup', ['title' => 'Instalación', 'admin' => true], 'layout_guest');
    }

    public static function registerForm(): void
    {
        if (!Config::get('app.allow_registration')) {
            View::redirect('/login');
        }
        View::render('auth/setup', ['title' => 'Crear cuenta', 'admin' => false], 'layout_guest');
    }

    public static function createUser(): void
    {
        Csrf::verify();
        $isFirst = (int) (Database::one('SELECT COUNT(*) c FROM users')['c'] ?? 0) === 0;
        if (!$isFirst && !Config::get('app.allow_registration')) {
            View::redirect('/login');
        }
        $back = $isFirst ? '/instalar' : '/registro';
        $name = Request::str('name', '', 120);
        $email = mb_strtolower(Request::str('email', '', 190));
        $pass = Request::str('password', '', 500);
        $tz = Request::str('timezone', (string) Config::get('app.default_timezone'), 64);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Completá nombre y un email válido.');
            View::redirect($back);
        }
        if (mb_strlen($pass) < 12) {
            Session::flash('error', 'La contraseña debe tener al menos 12 caracteres.');
            View::redirect($back);
        }
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = (string) Config::get('app.default_timezone');
        }
        if (Database::one('SELECT id FROM users WHERE email = ?', [$email])) {
            Session::flash('error', 'Ya existe una cuenta con ese email.');
            View::redirect($back);
        }
        $id = Database::insert('users', [
            'name' => $name, 'email' => $email, 'password_hash' => Auth::hash($pass),
            'role' => $isFirst ? 'admin' : 'user', 'timezone' => $tz, 'webhook_token' => Crypto::token(32),
        ]);
        Audit::log('user_created', ['email' => $email], $id);
        Auth::login(Database::one('SELECT * FROM users WHERE id = ?', [$id]));
        Session::flash('ok', '¡Bienvenido! Conectá tus cuentas en Configuración para empezar.');
        View::redirect('/configuracion');
    }
}
