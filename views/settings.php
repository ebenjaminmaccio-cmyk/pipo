<?php use App\Core\{Auth, Csrf, View}; $u = Auth::user(); ?>
<h1>Configuración</h1>
<div class="grid-2">
    <div class="stack">
        <section class="card">
            <h2>Cuentas conectadas</h2>
            <ul class="list">
                <?php foreach ($integrations as $i): ?>
                    <li class="row between"><span><?= ['google' => '🟢 Google (Gmail + Calendar)', 'imap' => '📮 IMAP/SMTP', 'whatsapp_crm' => '🍋 Lime CRM'][$i['provider']] ?> · <b><?= View::e($i['label']) ?></b></span>
                        <form method="post" action="/configuracion/desconectar" data-confirm="¿Desconectar esta cuenta? Se borrarán sus mails descargados."><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><button class="link danger small">Desconectar</button></form></li>
                <?php endforeach; ?>
                <?php if (!$integrations): ?><li class="muted">Ninguna todavía.</li><?php endif; ?>
            </ul>

            <h3>Conectar Google</h3>
            <?php if ($googleReady): ?>
                <form method="post" action="/google/conectar"><?= Csrf::field() ?><button class="btn primary">Conectar Gmail y Google Calendar</button></form>
            <?php else: ?>
                <p class="muted small">Falta configurar <code>google.client_id</code> y <code>google.client_secret</code> en config.php (ver README).</p>
            <?php endif; ?>

            <details><summary><h3 class="inline">Conectar otra casilla (IMAP/SMTP)</h3></summary>
                <form method="post" action="/configuracion/imap" class="stack" data-loading="Probando conexión…">
                    <?= Csrf::field() ?>
                    <label>Email (usuario) <input type="email" name="username" required></label>
                    <label>Contraseña (o contraseña de aplicación) <input type="password" name="password" required autocomplete="off"></label>
                    <div class="row"><label class="grow">Servidor IMAP <input name="imap_host" placeholder="imap.hostinger.com" required></label>
                        <label>Puerto <input name="imap_port" value="993" size="4"></label>
                        <label>Cifrado <select name="imap_encryption"><option>ssl</option><option>tls</option></select></label></div>
                    <div class="row"><label class="grow">Servidor SMTP <input name="smtp_host" placeholder="smtp.hostinger.com" required></label>
                        <label>Puerto <input name="smtp_port" value="465" size="4"></label>
                        <label>Cifrado <select name="smtp_encryption"><option>ssl</option><option>tls</option></select></label></div>
                    <p class="muted small">Outlook: outlook.office365.com (993) / smtp.office365.com (587, tls). La contraseña se guarda cifrada.</p>
                    <button class="btn">Conectar</button>
                </form>
            </details>
        </section>

        <section class="card" id="lime">
            <h2>Lime CRM (WhatsApp)</h2>
            <p class="muted small">Pipo lee los mensajes de WhatsApp, Instagram y Messenger de tu empresa en Lime y los resume. Es de solo lectura: no puede enviar nada desde Lime.</p>
            <form method="post" action="/configuracion/lime" class="stack" data-loading="Conectando…">
                <?= Csrf::field() ?>
                <label>Dirección de Lime <input name="url" value="https://crmlime.com" required></label>
                <label>Token de Pipo (lo genera Lime, 64 caracteres) <input name="token" required autocomplete="off" pattern="[a-fA-F0-9]{64}" class="mono"></label>
                <button class="btn">Conectar Lime CRM</button>
            </form>
            <p class="muted small">¿Cómo obtengo el token? Ver <code>integraciones/lime-crm/README.md</code>.</p>
        </section>

        <section class="card" id="memoria">
            <h2>Memoria del asistente</h2>
            <p class="muted small">Lo que Pipo sabe de vos. Lo usa en cada conversación.</p>
            <ul class="list">
                <?php foreach ($memories as $m): ?>
                    <li class="row between"><span><?= View::e($m['fact']) ?></span>
                        <form method="post" action="/configuracion/memoria"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="do" value="del"><button class="link danger small">Olvidar</button></form></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="/configuracion/memoria" class="row"><?= Csrf::field() ?><input type="hidden" name="do" value="add">
                <input name="fact" placeholder="Ej: Mi socio se llama Martín (martin@empresa.com)" class="grow" maxlength="1000"><button class="btn small">Agregar</button></form>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Perfil</h2>
            <form method="post" action="/configuracion/perfil" class="stack">
                <?= Csrf::field() ?>
                <label>Nombre <input name="name" value="<?= View::e($u['name']) ?>" required></label>
                <label>Zona horaria <select name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $tz === $u['timezone'] ? 'selected' : '' ?>><?= View::e($tz) ?></option><?php endforeach; ?></select></label>
                <label>Hora del resumen diario <input type="number" name="digest_hour" min="0" max="23" value="<?= (int) $u['digest_hour'] ?>"></label>
                <details><summary>Cambiar contraseña</summary>
                    <label>Contraseña actual <input type="password" name="current_password" autocomplete="current-password"></label>
                    <label>Nueva contraseña (12+ caracteres) <input type="password" name="new_password" minlength="12" autocomplete="new-password"></label>
                </details>
                <button class="btn">Guardar</button>
            </form>
        </section>

        <section class="card" id="seguridad">
            <h2>Verificación en dos pasos</h2>
            <?php if ((int) $u['totp_enabled'] === 1): ?>
                <p>✅ Activada.</p>
                <form method="post" action="/configuracion/2fa" class="row"><?= Csrf::field() ?><input type="hidden" name="do" value="disable">
                    <input type="password" name="password" placeholder="Tu contraseña" required><button class="btn small ghost danger">Desactivar</button></form>
            <?php elseif ($totpSecret): ?>
                <p class="small">1. En Google Authenticator / Authy tocá "+" → "Ingresar clave de configuración" y cargá:</p>
                <input readonly class="mono" value="<?= View::e($totpSecret) ?>" data-copy>
                <p class="small muted">O usá este enlace en el celular: <a href="<?= View::e($totpUri) ?>">agregar a la app</a></p>
                <form method="post" action="/configuracion/2fa" class="row"><?= Csrf::field() ?><input type="hidden" name="do" value="confirm">
                    <input name="code" placeholder="Código de 6 dígitos" inputmode="numeric" required><button class="btn small primary">2. Confirmar</button></form>
            <?php else: ?>
                <p class="muted small">Muy recomendado: protege tu mail y tus datos aunque te roben la contraseña.</p>
                <form method="post" action="/configuracion/2fa"><?= Csrf::field() ?><input type="hidden" name="do" value="start"><button class="btn">Activar</button></form>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Consumo de IA (30 días)</h2>
            <p><?= number_format((int) $usage['calls'], 0, ',', '.') ?> consultas · <?= number_format((int) $usage['inp'], 0, ',', '.') ?> tokens de entrada · <?= number_format((int) $usage['outp'], 0, ',', '.') ?> de salida</p>
        </section>
    </div>
</div>
