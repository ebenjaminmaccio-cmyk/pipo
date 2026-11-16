<?php
declare(strict_types=1);

/*
 * Ejecutar cada 5 minutos desde el panel del hosting (hPanel -> Avanzado -> Cron Jobs):
 *   /usr/bin/php /home/USUARIO/pipo/bin/cron.php
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$lock = fopen(APP_ROOT . '/storage/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // ya hay otra ejecución en curso
}
$stats = App\Services\CronService::run();
echo date('c') . ' ' . json_encode($stats) . PHP_EOL;
flock($lock, LOCK_UN);
