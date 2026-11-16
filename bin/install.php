<?php
declare(strict_types=1);

/* Crea las tablas en la base de datos: php bin/install.php */
if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$sql = file_get_contents(APP_ROOT . '/database/schema.sql');
$pdo = App\Core\Database::pdo();
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
    $pdo->exec($stmt);
}
echo "Tablas creadas. Ahora entrá a /instalar en el navegador para crear tu usuario.\n";
