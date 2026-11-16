<?php use App\Core\{Csrf, View}; $dias = ['Mon' => 'Lunes', 'Tue' => 'Martes', 'Wed' => 'Miércoles', 'Thu' => 'Jueves', 'Fri' => 'Viernes', 'Sat' => 'Sábado', 'Sun' => 'Domingo']; ?>
<div class="card-head">
    <h1>Agenda</h1>
    <div class="row">
        <a class="btn small ghost" href="/agenda?semana=<?= $offset - 1 ?>">← Anterior</a>
        <a class="btn small ghost" href="/agenda">Esta semana</a>
        <a class="btn small ghost" href="/agenda?semana=<?= $offset + 1 ?>">Siguiente →</a>
    </div>
</div>
<div class="grid-2">
    <section class="week">
        <?php foreach ($days as $date => $events): $d = new DateTimeImmutable($date); ?>
            <div class="day card <?= $date === (new DateTimeImmutable('now', new DateTimeZone(App\Core\Auth::user()['timezone'])))->format('Y-m-d') ? 'today' : '' ?>">
                <h3><?= $dias[$d->format('D')] ?> <span class="muted"><?= $d->format('d/m') ?></span></h3>
                <?php if (!$events): ?><p class="muted small">Libre</p><?php endif; ?>
                <?php foreach ($events as $e): ?>
                    <div class="event">
                        <b><?= View::date($e['starts_at'], 'H:i') ?>–<?= View::date($e['ends_at'], 'H:i') ?></b> <?= View::e($e['title']) ?>
                        <?php if ($e['source'] === 'google'): ?><span class="badge">Google</span><?php endif; ?>
                        <?php if ($e['location']): ?><div class="muted small"><?= View::e($e['location']) ?></div><?php endif; ?>
                        <?php if ($e['id']): ?>
                            <form method="post" action="/agenda/borrar" class="inline" data-confirm="¿Eliminar el evento?"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><button class="link danger small">Eliminar</button></form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <h2>Nueva reunión</h2>
        <form method="post" action="/agenda" class="stack">
            <?= Csrf::field() ?>
            <label>Título <input name="title" required maxlength="255"></label>
            <label>Inicio <input type="datetime-local" name="starts_at" required></label>
            <label>Fin <input type="datetime-local" name="ends_at" required></label>
            <label>Lugar o link <input name="location" maxlength="255"></label>
            <label>Invitados (emails separados por coma) <input name="attendees"></label>
            <label>Descripción <textarea name="description" rows="3"></textarea></label>
            <label>Avisarme <select name="remind_minutes"><option value="30">30 min antes</option><option value="10">10 min antes</option><option value="60">1 hora antes</option><option value="1440">1 día antes</option><option value="0">No avisar</option></select></label>
            <button class="btn primary">Agendar</button>
        </form>
        <p class="muted small">Tip: también podés pedírselo al asistente: "agendame con Ana el viernes a las 15".</p>
    </section>
</div>
