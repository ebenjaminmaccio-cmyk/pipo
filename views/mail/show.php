<?php use App\Core\{Csrf, View}; $e = $email; ?>
<p><a href="/mail">← Volver</a></p>
<section class="card">
    <h1><?= View::e($e['subject']) ?></h1>
    <p class="muted">De <b><?= View::e(trim(($e['from_name'] ?? '') . ' <' . $e['from_email'] . '>')) ?></b> · <?= View::date($e['received_at']) ?> · <?= View::e($e['account']) ?></p>

    <div class="card soft">
        <div class="card-head"><h3>Resumen IA</h3>
            <form method="post" action="/mail/<?= (int) $e['id'] ?>/resumir" data-loading="Resumiendo…"><?= Csrf::field() ?><button class="btn small"><?= $e['ai_summary'] ? 'Rehacer' : 'Resumir' ?></button></form></div>
        <?php if ($e['ai_summary']): ?><div class="prose" data-markdown><?= View::e($e['ai_summary']) ?></div><?php else: ?><p class="muted small">Tocá "Resumir" para obtener los puntos clave.</p><?php endif; ?>
    </div>

    <pre class="mail-body"><?= View::e($e['body_text']) ?></pre>

    <details open><summary>↩️ Responder</summary>
        <form method="post" action="/mail/redactar" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="account_id" value="<?= (int) $e['integration_id'] ?>">
            <input type="hidden" name="email_id" value="<?= (int) $e['id'] ?>">
            <label>Para <input name="to" value="<?= View::e($e['from_email']) ?>" required></label>
            <label>Asunto <input name="subject" value="<?= View::e(str_starts_with(mb_strtolower((string) $e['subject']), 're:') ? $e['subject'] : 'Re: ' . $e['subject']) ?>" required></label>
            <label>Mensaje <textarea name="body" rows="6" required></textarea></label>
            <div class="row"><button class="btn">Guardar borrador</button>
                <a class="btn ghost" href="/asistente?q=<?= rawurlencode('Redactá una respuesta para el mail id ' . $e['id']) ?>">Que lo redacte el asistente</a></div>
        </form>
    </details>
</section>
