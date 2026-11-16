<?php use App\Core\{Auth, Csrf, View}; $u = Auth::user(); ?>
<h1>Hola, <?= View::e(explode(' ', $u['name'])[0]) ?> 👋</h1>

<?php if (!$hasAccounts): ?>
    <div class="card highlight">
        <strong>Primer paso:</strong> conectá tu Gmail o tu casilla de mail en <a href="/configuracion">Configuración</a>.
    </div>
<?php endif; ?>

<div class="stats">
    <a class="stat" href="/mail"><b><?= $unread ?></b><span>mails sin leer</span></a>
    <a class="stat" href="/mail#borradores"><b><?= $drafts ?></b><span>borradores por aprobar</span></a>
    <a class="stat" href="/tareas"><b><?= count($tasks) ?></b><span>tareas pendientes</span></a>
    <a class="stat" href="/whatsapp"><b><?= $wa24 ?></b><span>mensajes WhatsApp (24 h)</span></a>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Resumen del día</h2>
            <form method="post" action="/resumen" data-loading="Generando…"><?= Csrf::field() ?><button class="btn small">Generar ahora</button></form>
        </div>
        <?php if ($digest): ?>
            <p class="muted small">Generado: <?= View::date($digest['created_at']) ?></p>
            <div class="prose" data-markdown><?= View::e($digest['content']) ?></div>
        <?php else: ?>
            <p class="muted">Todavía no hay resumen. Se genera solo cada mañana (o tocá "Generar ahora").</p>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Hoy</h2><a href="/agenda" class="small">Ver agenda</a></div>
            <?php if (!$events): ?><p class="muted">Sin reuniones hoy.</p><?php endif; ?>
            <ul class="list">
                <?php foreach ($events as $e): ?>
                    <li><b><?= View::date($e['starts_at'], 'H:i') ?></b> <?= View::e($e['title']) ?>
                        <?php if ($e['location']): ?><span class="muted small">· <?= View::e($e['location']) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="card">
            <div class="card-head"><h2>Tareas</h2><a href="/tareas" class="small">Ver todas</a></div>
            <?php if (!$tasks): ?><p class="muted">¡Nada pendiente!</p><?php endif; ?>
            <ul class="list">
                <?php foreach ($tasks as $t): ?>
                    <li><span class="badge p-<?= View::e($t['priority']) ?>"><?= View::e($t['priority']) ?></span> <?= View::e($t['title']) ?>
                        <?php if ($t['due_at']): ?><span class="muted small">· vence <?= View::date($t['due_at']) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <a href="/asistente" class="btn primary block">💬 Hablar con el asistente</a>
    </div>
</div>
