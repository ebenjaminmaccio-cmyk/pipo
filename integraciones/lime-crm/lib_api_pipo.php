<?php
// API externa de solo lectura para Pipo (asistente personal). Ver integraciones/lime-crm/README.md en el repo de Pipo.
//
// GET api.php?action=pipo_mensajes&desde_id=0&limite=200
// Cabecera: Authorization: Bearer <token>   (token creado con scripts/crear_token_pipo.php)
//
// Devuelve los mensajes de clientes (WhatsApp, Instagram, Messenger) de la empresa del token, en orden de id,
// a partir de desde_id (cursor). Pipo guarda el último id y pide solo lo nuevo.
// No incluye: notas internas, charlas internas de Slack, ni mensajes eliminados.
// Con alcance 'mis_chats', solo los chats asignados al usuario dueño del token.

const PIPO_LIMITE_MAX = 500;

function pipoTokenDesdeCabecera(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $h = $v;
    }
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim((string)$h), $m) ? strtolower($m[1]) : '';
}

// Valida el token y devuelve ['id','empresa_id','usuario_id','alcance'] o corta con 401.
function pipoAutenticar(): array {
    $token = pipoTokenDesdeCabecera();
    if ($token === '') err(401, 'Falta el token');
    $st = db()->prepare(
        "SELECT t.id, t.empresa_id, t.usuario_id, t.alcance
         FROM api_tokens t
         JOIN usuarios u ON u.id = t.usuario_id AND u.activo = 1
         JOIN empresas e ON e.id = t.empresa_id AND e.activa = 1
         WHERE t.token_hash = ? AND t.revocado_en IS NULL"
    );
    $st->execute([hash('sha256', $token)]);
    $t = $st->fetch();
    if (!$t) err(401, 'Token inválido');
    db()->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = ?')->execute([$t['id']]);
    return $t;
}

function action_pipo_mensajes(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') err(405, 'Método no permitido');
    $t = pipoAutenticar();
    $desde  = max(0, (int)($_GET['desde_id'] ?? 0));
    $limite = max(1, min(PIPO_LIMITE_MAX, (int)($_GET['limite'] ?? 200)));

    $sql = "SELECT m.id, m.chat_id, m.rol, m.tipo, m.contenido, UNIX_TIMESTAMP(m.created_at) AS ts,
                   ch.canal, c.nombre AS contacto, c.telefono
            FROM mensajes m
            JOIN chats ch    ON ch.id = m.chat_id
            JOIN contactos c ON c.id = ch.contacto_id
            WHERE ch.empresa_id = ?
              AND m.id > ?
              AND ch.canal IN ('whatsapp','instagram','facebook')
              AND ch.slack_tipo IS NULL
              AND m.tipo <> 'nota'
              AND m.eliminado_en IS NULL";
    $params = [(int)$t['empresa_id'], $desde];
    if ($t['alcance'] === 'mis_chats') {
        $sql .= ' AND ch.asignado_a = ?';
        $params[] = (int)$t['usuario_id'];
    }
    $sql .= ' ORDER BY m.id ASC LIMIT ' . $limite;
    $st = db()->prepare($sql);
    $st->execute($params);

    $mensajes = [];
    $ultimo = $desde;
    foreach ($st->fetchAll() as $r) {
        $ultimo = (int)$r['id'];
        $mensajes[] = [
            'id'        => (int)$r['id'],
            'chat_id'   => (int)$r['chat_id'],
            'canal'     => $r['canal'],
            'contacto'  => $r['contacto'],
            'telefono'  => $r['telefono'],
            'direccion' => $r['rol'] === 'usuario' ? 'in' : 'out',   // usuario = cliente; agente/bot/app = la empresa
            'autor'     => $r['rol'],
            'tipo'      => $r['tipo'],
            'texto'     => (string)($r['contenido'] ?? ''),
            'timestamp' => (int)$r['ts'],
        ];
    }
    ok(['mensajes' => $mensajes, 'siguiente_desde_id' => $ultimo, 'hay_mas' => count($mensajes) === $limite]);
}
