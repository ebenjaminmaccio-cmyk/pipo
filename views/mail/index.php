<?php use App\Core\{Csrf, View}; ?>
<div class="card-head">
    <h1>Mail</h1>
    <form method="post" action="/mail/sincronizar" data-loading="Descargando…"><?= Csrf::field() ?><button class="btn small">↻ Actualizar</button></form>
</div>

<?php if (!$accounts): ?>
    <div class="card highlight">No hay cuentas conectadas. Conectá Gmail o una casilla IMAP en <a href="/configuracion">Configuración</a>.</div>
<?php endif; ?>

<section class="card" id="borradores">
    <h2>Borradores por aprobar <span class="badge"><?= count($drafts) ?></span></h2>
    <p class="muted small">El asistente nunca envía mails solo. Revisá, editá y tocá "Enviar".</p>
    <?php foreach ($drafts as $d): ?>
        <form method="post" action="/borrador/<?= (int) $d['id'] ?>" class="draft stack">
            <?= Csrf::field() ?>
            <div class="muted small">Desde <?= View::e($d['account']) ?> · <?= View::date($d['created_at']) ?>
                <?php if ($d['status'] === 'error'): ?><span class="badge p-alta">Error: <?= View::e($d['error']) ?></span><?php endif; ?></div>
            <label>Para <input name="to" value="<?= View::e($d['to_email']) ?>" required></label>
            <label>Asunto <input name="subject" value="<?= View::e($d['subject']) ?>" required></label>
            <label>Mensaje <textarea name="body" rows="8"><?= View::e($d['body_text']) ?></textarea></label>
            <div class="row">
                <button class="btn primary" name="do" value="send" data-confirm-click="¿Enviar este mail?">Enviar</button>
                <button class="btn ghost" name="do" value="save">Guardar cambios</button>
                <button class="btn ghost danger" name="do" value="discard">Descartar</button>
            </div>
        </form>
    <?php endforeach; ?>
    <?php if ($accounts): ?>
        <details><summary>✉️ Redactar mail nuevo</summary>
            <form method="post" action="/mail/redactar" class="stack">
                <?= Csrf::field() ?>
                <label>Desde <select name="account_id"><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= View::e($a['label']) ?></option><?php endforeach; ?></select></label>
                <label>Para <input name="to" required></label>
                <label>Asunto <input name="subject" required></label>
                <label>Mensaje <textarea name="body" rows="6" required></textarea></label>
                <button class="btn">Guardar borrador</button>
            </form>
        </details>
    <?php endif; ?>
</section>

<section class="card">
    <form method="get" action="/mail" class="row"><input name="q" value="<?= View::e($q) ?>" placeholder="Buscar por remitente o asunto"><button class="btn small">Buscar</button></form>
    <ul class="list mails">
        <?php foreach ($emails as $e): ?>
            <li class="<?= $e['is_read'] ? '' : 'unread' ?>">
                <a href="/mail/<?= (int) $e['id'] ?>">
                    <div class="row between"><b><?= View::e($e['from_name'] ?: $e['from_email']) ?></b><span class="muted small"><?= View::date($e['received_at']) ?></span></div>
                    <div><?php if ($e['ai_priority']): ?><span class="badge p-<?= View::e($e['ai_priority']) ?>"><?= View::e($e['ai_priority']) ?></span> <?php endif; ?><?= View::e($e['subject']) ?></div>
                    <div class="muted small"><?= View::e($e['snippet']) ?></div>
                </a>
            </li>
        <?php endforeach; ?>
        <?php if (!$emails): ?><li class="muted">No hay mails descargados todavía.</li><?php endif; ?>
    </ul>
</section>
