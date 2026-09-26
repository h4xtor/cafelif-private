<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['selector'], $_GET['token'])) {
    $incomingSelector = strtolower(trim((string)$_GET['selector']));
    $incomingToken = strtolower(trim((string)$_GET['token']));
    if (password_reset_link_is_valid($incomingSelector, $incomingToken)) {
        session_regenerate_id(true);
        $_SESSION['admin_password_reset'] = [
            'selector' => $incomingSelector,
            'token' => $incomingToken,
        ];
    } else {
        unset($_SESSION['admin_password_reset']);
    }
    redirect('/admin/reset-password.php');
}

$resetState = $_SESSION['admin_password_reset'] ?? [];
$selector = strtolower(trim((string)($resetState['selector'] ?? '')));
$token = strtolower(trim((string)($resetState['token'] ?? '')));
$valid = password_reset_link_is_valid($selector, $token);
if (!$valid) {
    unset($_SESSION['admin_password_reset']);
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $repeat = (string)($_POST['password_repeat'] ?? '');

    if (mb_strlen($password) < 12) {
        $error = 'Adgangskoden skal være mindst 12 tegn.';
    } elseif ($password !== $repeat) {
        $error = 'Adgangskoderne er ikke ens.';
    } elseif (consume_admin_password_reset($selector, $token, $password)) {
        unset($_SESSION['admin_password_reset']);
        flash('success', 'Adgangskoden er ændret. Du kan nu logge ind med den nye kode.');
        redirect('/admin/login.php');
    } else {
        $valid = false;
        $error = 'Linket er ugyldigt, brugt eller udløbet.';
    }
}
?><!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vælg ny adgangskode · Café LIF</title>
  <link rel="stylesheet" href="<?= h(base_url('/assets/css/admin.css')) ?>">
</head>
<body class="login-body">
<main class="login-card">
  <img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt="Café LIF">
  <h1>Vælg ny adgangskode</h1>
  <?php if (!$valid): ?>
    <div class="alert alert--error">Linket er ugyldigt, brugt eller udløbet.</div>
    <p><a class="button button--primary" href="<?= h(base_url('/admin/forgot-password.php')) ?>">Bed om et nyt link</a></p>
  <?php else: ?>
    <p>Vælg en ny adgangskode på mindst 12 tegn.</p>
    <?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>Ny adgangskode
        <input type="password" name="password" minlength="12" autocomplete="new-password" required autofocus>
      </label>
      <label>Gentag adgangskode
        <input type="password" name="password_repeat" minlength="12" autocomplete="new-password" required>
      </label>
      <button class="button button--primary" type="submit">Gem ny adgangskode</button>
    </form>
  <?php endif; ?>
  <p><a href="<?= h(base_url('/admin/login.php')) ?>">Tilbage til login</a></p>
</main>
</body>
</html>
