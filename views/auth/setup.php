<?php use App\Core\{Config, Csrf, View}; ?>
<form method="post" action="<?= $admin ? '/instalar' : '/registro' ?>" class="stack">
    <?= Csrf::field() ?>
    <?php if ($admin): ?><p>Bienvenido. Creá tu usuario administrador.</p><?php endif; ?>
    <label>Nombre <input name="name" required maxlength="120"></label>
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Contraseña (mínimo 12 caracteres) <input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
    <label>Zona horaria <input name="timezone" value="<?= View::e(Config::get('app.default_timezone')) ?>"></label>
    <button class="btn primary">Crear cuenta</button>
</form>
