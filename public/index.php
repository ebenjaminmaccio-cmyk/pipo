<?php
declare(strict_types=1);

/*
 * Front controller: TODAS las peticiones pasan por acá.
 * En el hosting, el contenido de /public va dentro de public_html y el resto del proyecto
 * queda un nivel arriba (fuera del alcance web). Si tu estructura es distinta, ajustá APP_PATH.
 */
// Servidor de desarrollo (php -S): servir archivos estáticos directamente
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$appPath = is_file(__DIR__ . '/../src/bootstrap.php') ? __DIR__ . '/..' : __DIR__ . '/../pipo';
require $appPath . '/src/bootstrap.php';

use App\Controllers as C;
use App\Core\{Request, Session};

// Cabeceras de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self' https://accounts.google.com");
if (Request::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$method = Request::method();
$path = Request::path();

// Rutas sin sesión
if ($path === '/webhook/whatsapp') {
    C\WebhookController::whatsapp();
}
if ($path === '/cron') {
    $token = (string) App\Core\Config::get('cron_token', '');
    if (strlen($token) < 24 || str_contains($token, 'CAMBIAR') || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('forbidden');
    }
    @set_time_limit(300);
    header('Content-Type: application/json');
    echo json_encode(App\Services\CronService::run());
    exit;
}

Session::start();

$routes = [
    'GET /'                     => [C\DashboardController::class, 'index'],
    'POST /resumen'             => [C\DashboardController::class, 'digest'],
    'GET /login'                => [C\AuthController::class, 'loginForm'],
    'POST /login'               => [C\AuthController::class, 'login'],
    'GET /login/2fa'            => [C\AuthController::class, 'twoFactorForm'],
    'POST /login/2fa'           => [C\AuthController::class, 'twoFactor'],
    'POST /logout'              => [C\AuthController::class, 'logout'],
    'GET /instalar'             => [C\AuthController::class, 'setupForm'],
    'POST /instalar'            => [C\AuthController::class, 'createUser'],
    'GET /registro'             => [C\AuthController::class, 'registerForm'],
    'POST /registro'            => [C\AuthController::class, 'createUser'],
    'GET /asistente'            => [C\ChatController::class, 'index'],
    'POST /asistente/enviar'    => [C\ChatController::class, 'send'],
    'POST /asistente/borrar'    => [C\ChatController::class, 'clear'],
    'GET /tareas'               => [C\TaskController::class, 'index'],
    'POST /tareas'              => [C\TaskController::class, 'store'],
    'POST /tareas/accion'       => [C\TaskController::class, 'action'],
    'GET /agenda'               => [C\CalendarController::class, 'index'],
    'POST /agenda'              => [C\CalendarController::class, 'store'],
    'POST /agenda/borrar'       => [C\CalendarController::class, 'delete'],
    'GET /mail'                 => [C\MailController::class, 'index'],
    'POST /mail/sincronizar'    => [C\MailController::class, 'sync'],
    'POST /mail/redactar'       => [C\MailController::class, 'compose'],
    'GET /whatsapp'             => [C\WhatsAppController::class, 'index'],
    'POST /whatsapp/importar'   => [C\WhatsAppController::class, 'import'],
    'POST /whatsapp/resumir'    => [C\WhatsAppController::class, 'summarize'],
    'POST /whatsapp/sincronizar' => [C\WhatsAppController::class, 'sync'],
    'POST /configuracion/lime'  => [C\SettingsController::class, 'lime'],
    'GET /configuracion'        => [C\SettingsController::class, 'index'],
    'POST /configuracion/perfil'  => [C\SettingsController::class, 'profile'],
    'POST /configuracion/imap'    => [C\SettingsController::class, 'imap'],
    'POST /configuracion/desconectar' => [C\SettingsController::class, 'deleteIntegration'],
    'POST /configuracion/2fa'     => [C\SettingsController::class, 'totp'],
    'POST /configuracion/memoria' => [C\SettingsController::class, 'memory'],
    'POST /configuracion/webhook' => [C\SettingsController::class, 'rotateWebhook'],
    'POST /google/conectar'     => [C\GoogleController::class, 'connect'],
    'GET /google/callback'      => [C\GoogleController::class, 'callback'],
];

$key = $method . ' ' . $path;
if (isset($routes[$key])) {
    [$class, $fn] = $routes[$key];
    $class::$fn();
    exit;
}

// Rutas con parámetro numérico
$dynamic = [
    ['GET',  '#^/mail/(\d+)$#',           [C\MailController::class, 'show']],
    ['POST', '#^/mail/(\d+)/resumir$#',   [C\MailController::class, 'summarize']],
    ['POST', '#^/borrador/(\d+)$#',       [C\MailController::class, 'draftAction']],
];
foreach ($dynamic as [$m, $re, [$class, $fn]]) {
    if ($m === $method && preg_match($re, $path, $mm)) {
        $class::$fn($mm[1]);
        exit;
    }
}

http_response_code(404);
echo 'Página no encontrada';
