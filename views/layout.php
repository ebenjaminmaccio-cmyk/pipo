<?php use App\Core\{Auth, Csrf, Session, View}; $u = Auth::user(); $path = App\Core\Request::path(); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
    <title><?= View::e($title ?? 'Pipo') ?> · Pipo</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a href="/" class="brand"><span class="logo">P</span> Pipo</a>
    <nav class="nav">
        <?php foreach (['/' => 'Inicio', '/asistente' => 'Asistente', '/agenda' => 'Agenda', '/tareas' => 'Tareas', '/mail' => 'Mail', '/whatsapp' => 'WhatsApp', '/configuracion' => 'Configuración'] as $href => $label): ?>
            <a href="<?= $href ?>" class="<?= ($href === '/' ? $path === '/' : str_starts_with($path, $href)) ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="/logout" class="logout"><?= Csrf::field() ?><button class="link">Salir</button></form>
</header>
<main class="container">
    <?php foreach (Session::takeFlash() as $f): ?>
        <div class="flash flash-<?= View::e($f['type']) ?>"><?= View::e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<script src="/assets/app.js"></script>
</body>
</html>
