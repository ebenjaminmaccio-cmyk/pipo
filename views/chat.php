<?php use App\Core\{Csrf, View}; ?>
<div class="chat-wrap">
    <div class="card-head">
        <h1>Asistente</h1>
        <form method="post" action="/asistente/borrar" data-confirm="¿Borrar toda la conversación?"><?= Csrf::field() ?><button class="btn small ghost">Nueva conversación</button></form>
    </div>
    <div id="chat-log" class="chat-log">
        <?php if (!$history): ?>
            <div class="msg assistant"><div class="bubble">¡Hola! Soy Pipo. Podés pedirme cosas como:
                <ul class="suggestions">
                    <li data-suggest>¿Qué tengo hoy en la agenda?</li>
                    <li data-suggest>Agendame una reunión con Juan el jueves a las 10</li>
                    <li data-suggest>Resumime los mails sin leer</li>
                    <li data-suggest>¿Qué me escribieron por WhatsApp hoy?</li>
                    <li data-suggest>Recordame mañana a las 9 llamar al contador</li>
                </ul></div></div>
        <?php endif; ?>
        <?php foreach ($history as $m): ?>
            <div class="msg <?= View::e($m['role']) ?>"><div class="bubble" <?= $m['role'] === 'assistant' ? 'data-markdown' : '' ?>><?= View::e($m['content']) ?></div></div>
        <?php endforeach; ?>
    </div>
    <form id="chat-form" class="chat-form">
        <textarea id="chat-input" rows="1" placeholder="Escribí lo que necesites…" maxlength="8000" required></textarea>
        <button class="btn primary" id="chat-send">Enviar</button>
    </form>
</div>
