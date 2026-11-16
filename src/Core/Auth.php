<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }
        self::$user = Database::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        return self::$user;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function require(): array
    {
        $u = self::user();
        if ($u === null) {
            View::redirect('/login');
        }
        return $u;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    /**
     * Verifica email+contraseña con límite de intentos.
     * @return array<string,mixed>|string  usuario o mensaje de error
     */
    public static function attempt(string $email, string $password): array|string
    {
        $email = mb_strtolower(trim($email));
        $ip = Request::ipBinary();

        $fails = Database::one(
            'SELECT COUNT(*) c FROM login_attempts WHERE success = 0 AND created_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE) AND (ip = ? OR email = ?)',
            [$ip, $email]
        );
        if ((int) ($fails['c'] ?? 0) >= 5) {
            return 'Demasiados intentos. Esperá 15 minutos.';
        }

        $user = Database::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
        // Se verifica contra un hash falso si no existe el usuario para no filtrar qué emails existen por tiempo de respuesta
        $hash = $user['password_hash'] ?? self::hash(Crypto::token(8));
        $ok = password_verify($password, $hash) && $user !== null;

        Database::insert('login_attempts', ['ip' => $ip, 'email' => $email, 'success' => $ok ? 1 : 0]);
        if (!$ok) {
            return 'Email o contraseña incorrectos.';
        }
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hash($password), $user['id']]);
        }
        return $user;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::forget('pending_2fa');
        Database::run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        Audit::log('login', [], (int) $user['id']);
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }
}
