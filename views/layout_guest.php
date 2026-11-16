<?php use App\Core\{Csrf, Session, View}; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? 'Pipo') ?> · Pipo</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="guest">
<main class="auth-card">
    <div class="brand big"><span class="logo">P</span> Pipo</div>
    <p class="muted">Tu asistente personal</p>
    <?php foreach (Session::takeFlash() as $f): ?>
        <div class="flash flash-<?= View::e($f['type']) ?>"><?= View::e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>
