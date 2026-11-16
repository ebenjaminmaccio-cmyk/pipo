<?php use App\Core\{Config, Csrf}; ?>
<form method="post" action="/login" class="stack">
    <?= Csrf::field() ?>
    <label>Email <input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Contraseña <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn primary">Ingresar</button>
    <?php if (Config::get('app.allow_registration')): ?><a href="/registro" class="muted center">Crear una cuenta</a><?php endif; ?>
</form>
