<?php
// Crea un token de solo lectura para que Pipo lea los mensajes de una empresa de Lime.
// Uso (por SSH, en la carpeta de Lime):
//   php scripts/crear_token_pipo.php <empresa_id> <email_usuario> [empresa|mis_chats]
// Muestra el token UNA sola vez: copialo en Pipo → Configuración → Conectar Lime CRM.
// Para revocarlo: UPDATE api_tokens SET revocado_en = NOW() WHERE id = <id>;
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../db.php';

[$_, $empresaId, $email, $alcance] = $argv + [null, null, null, 'empresa'];
if (!ctype_digit((string)$empresaId) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($alcance, ['empresa', 'mis_chats'], true)) {
    fwrite(STDERR, "Uso: php scripts/crear_token_pipo.php <empresa_id> <email_usuario> [empresa|mis_chats]\n");
    exit(1);
}
$st = db()->prepare('SELECT u.id, u.nombre, e.nombre AS empresa FROM usuarios u JOIN empresas e ON e.id = ? WHERE u.email = ? AND u.activo = 1');
$st->execute([(int)$empresaId, $email]);
$u = $st->fetch();
if (!$u) {
    fwrite(STDERR, "No encontré el usuario activo o la empresa.\n");
    exit(1);
}
$token = bin2hex(random_bytes(32));
db()->prepare('INSERT INTO api_tokens (empresa_id, usuario_id, nombre, token_hash, alcance) VALUES (?,?,?,?,?)')
    ->execute([(int)$empresaId, $u['id'], 'Pipo', hash('sha256', $token), $alcance]);
echo "Token para Pipo — empresa \"{$u['empresa']}\", usuario {$u['nombre']}, alcance $alcance:\n\n$token\n\nGuardalo ahora: no se vuelve a mostrar.\n";
