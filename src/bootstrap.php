<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Falta config/config.php. Copiá config/config.example.php y completalo.');
}
App\Core\Config::load(require $configFile);

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

$debug = (bool) App\Core\Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $debug ? $e->getMessage() : 'Error interno.']);
        return;
    }
    echo $debug ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : 'Ocurrió un error. Probá de nuevo en unos minutos.';
});
