<?php use App\Core\{Csrf, View}; ?>
<h1>Tareas y recordatorios</h1>
<div class="grid-2">
    <section class="card">
        <h2>Pendientes</h2>
        <?php if (!$pending): ?><p class="muted">No tenés tareas pendientes.</p><?php endif; ?>
        <ul class="list tasks">
            <?php foreach ($pending as $t): ?>
                <li>
                    <form method="post" action="/tareas/accion" class="inline"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="complete"><button class="check" title="Marcar como hecha">✓</button></form>
                    <div class="grow">
                        <span class="badge p-<?= View::e($t['priority']) ?>"><?= View::e($t['priority']) ?></span>
                        <b><?= View::e($t['title']) ?></b>
                        <?php if ($t['notes']): ?><div class="muted small"><?= nl2br(View::e($t['notes'])) ?></div><?php endif; ?>
                        <div class="muted small">
                            <?php if ($t['due_at']): ?>Vence <?= View::date($t['due_at']) ?><?php endif; ?>
                            <?php if ($t['remind_at']): ?> · ⏰ <?= View::date($t['remind_at']) ?><?= $t['reminded_at'] ? ' (avisado)' : '' ?><?php endif; ?>
                        </div>
                    </div>
                    <form method="post" action="/tareas/accion" class="inline" data-confirm="¿Eliminar la tarea?"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="delete"><button class="link danger">Borrar</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($done): ?>
            <details><summary class="muted">Completadas recientemente (<?= count($done) ?>)</summary>
                <ul class="list">
                    <?php foreach ($done as $t): ?>
                        <li class="done"><s><?= View::e($t['title']) ?></s>
                            <form method="post" action="/tareas/accion" class="inline"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="reopen"><button class="link small">Reabrir</button></form></li>
                    <?php endforeach; ?>
                </ul></details>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Nueva tarea</h2>
        <form method="post" action="/tareas" class="stack">
            <?= Csrf::field() ?>
            <label>Título <input name="title" required maxlength="255"></label>
            <label>Notas <textarea name="notes" rows="3"></textarea></label>
            <label>Prioridad <select name="priority"><option value="media">Media</option><option value="alta">Alta</option><option value="baja">Baja</option></select></label>
            <label>Vence <input type="datetime-local" name="due_at"></label>
            <label>Recordarme (aviso por mail) <input type="datetime-local" name="remind_at"></label>
            <button class="btn primary">Guardar</button>
        </form>
    </section>
</div>
