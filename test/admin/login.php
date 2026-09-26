<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (current_admin()) {
    redirect('/admin/index.php');
}

$error = null;
$flashes = pull_flashes();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (admin_login_is_rate_limited()) {
        $error = 'For mange loginforsøg. Vent 15 minutter og prøv igen.';
    } elseif (attempt_admin_login((string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect('/admin/index.php');
    } else {
        usleep(500000);
        $error = 'Forkert brugernavn/e-mail eller adgangskode.';
    }
}
?><!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login · Café LIF</title>
  <link rel="stylesheet" href="<?= h(base_url('/assets/css/admin.css')) ?>">
</head>
<body class="login-body">
<main class="login-card">
  <img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt="Café LIF">
  <h1>Velkommen tilbage</h1>
  <p>Log ind for at rette menu, priser, billeder og åbningstider.</p>
  <?php foreach ($flashes as $flash): ?>
    <div class="alert alert--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
  <?php endforeach; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Brugernavn eller e-mail
      <input type="text" name="login" autocomplete="username" required autofocus placeholder="Eva">
    </label>
    <label>Adgangskode
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="button button--primary" type="submit">Log ind</button>
  </form>
  <p><a href="<?= h(base_url('/admin/forgot-password.php')) ?>">Glemt adgangskode?</a></p>
  <p class="muted">Adminområdet er ikke synligt i hjemmesidens menu.</p>
</main>
</body>
</html>

