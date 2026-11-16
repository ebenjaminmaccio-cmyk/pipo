<?php use App\Core\{Csrf, View}; ?>
<h1>WhatsApp</h1>
<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>Chats</h2>
            <form method="post" action="/whatsapp/resumir" data-loading="Resumiendo…" class="row"><?= Csrf::field() ?>
                <select name="hours"><option value="24">Últimas 24 h</option><option value="72">Últimos 3 días</option><option value="168">Última semana</option></select>
                <button class="btn small">Resumir todo</button></form></div>
        <?php if (!$chats): ?><p class="muted">Todavía no hay mensajes. Conectá tu CRM o importá un chat.</p><?php endif; ?>
        <ul class="list">
            <?php foreach ($chats as $c): ?>
                <li class="<?= $chat === $c['chat'] ? 'selected' : '' ?>"><a href="/whatsapp?chat=<?= rawurlencode($c['chat']) ?>" class="row between">
                    <span><b><?= View::e($c['chat']) ?></b> <?php if ((int) $c['last_24h'] > 0): ?><span class="badge p-alta"><?= (int) $c['last_24h'] ?> hoy</span><?php endif; ?></span>
                    <span class="muted small"><?= View::date($c['last_at']) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="stack">
        <?php if ($summary): ?>
            <section class="card highlight"><h2>Resumen <?= $summary['chat'] ? 'de ' . View::e($summary['chat']) : 'general' ?></h2>
                <div class="prose" data-markdown><?= View::e($summary['text']) ?></div></section>
        <?php endif; ?>

        <?php if ($chat !== ''): ?>
            <section class="card">
                <div class="card-head"><h2><?= View::e($chat) ?></h2>
                    <form method="post" action="/whatsapp/resumir" data-loading="Resumiendo…"><?= Csrf::field() ?><input type="hidden" name="chat" value="<?= View::e($chat) ?>"><input type="hidden" name="hours" value="168"><button class="btn small">Resumir este chat</button></form></div>
                <div class="wa-log">
                    <?php foreach ($messages as $m): ?>
                        <div class="wa <?= $m['direction'] ?>"><div class="muted small"><?= View::e($m['direction'] === 'out' ? 'Yo' : $m['sender']) ?> · <?= View::date($m['sent_at']) ?></div><?= nl2br(View::e($m['body'])) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Conectar tu CRM</h2>
            <p class="small">Configurá en tu CRM un <b>webhook</b> que envíe cada mensaje de WhatsApp (POST, JSON) a esta URL secreta:</p>
            <input readonly value="<?= View::e($webhookUrl) ?>" class="mono" data-copy>
            <details><summary class="small">Formato aceptado</summary>
<pre class="mono small">{
  "chat": "Juan Pérez",
  "sender": "Juan Pérez",
  "body": "Hola, ¿mañana nos vemos?",
  "direction": "in",
  "timestamp": 1731700000,
  "id": "id-unico-del-mensaje"
}</pre>
                <p class="small muted">También acepta <code>{"messages":[...]}</code> y el formato oficial de WhatsApp Cloud API (Meta). Para Meta, usá esta misma URL y como "Verify token" el valor de <code>token</code>.</p>
            </details>
            <form method="post" action="/configuracion/webhook" data-confirm="La URL actual dejará de funcionar. ¿Continuar?"><?= Csrf::field() ?><button class="link small danger">Generar nueva URL</button></form>
        </section>

        <section class="card">
            <h2>Importar un chat</h2>
            <p class="small muted">En el celular: abrí el chat → ⋮ → Más → Exportar chat → Sin archivos. Subí el .txt acá.</p>
            <form method="post" action="/whatsapp/importar" enctype="multipart/form-data" class="stack">
                <?= Csrf::field() ?>
                <input type="file" name="file" accept=".txt,text/plain" required>
                <label>Nombre del chat (opcional) <input name="chat_name" maxlength="255"></label>
                <label>Tu nombre tal como aparece en el chat (opcional) <input name="my_name" maxlength="120"></label>
                <button class="btn">Importar</button>
            </form>
        </section>
    </div>
</div>
