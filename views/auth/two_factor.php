<?php use App\Core\Csrf; ?>
<form method="post" action="/login/2fa" class="stack">
    <?= Csrf::field() ?>
    <p>Ingresá el código de 6 dígitos de tu app de autenticación.</p>
    <label>Código <input name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus autocomplete="one-time-code"></label>
    <button class="btn primary">Verificar</button>
</form>
