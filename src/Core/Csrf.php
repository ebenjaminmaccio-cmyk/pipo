<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t)) {
            $t = Crypto::token(32);
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . View::e(self::token()) . '">';
    }

    /** Valida el token del formulario o de la cabecera X-CSRF-Token (fetch). */
    public static function verify(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Sesión expirada o solicitud inválida. Volvé atrás y recargá la página.');
        }
    }
}
