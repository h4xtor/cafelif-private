<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (current_admin()) {
    redirect('/admin/index.php');
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    request_admin_password_reset((string)($_POST['email'] ?? ''));
    $submitted = true;
}
?><!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Glemt adgangskode · Café LIF</title>
  <link rel="stylesheet" href="<?= h(base_url('/assets/css/admin.css')) ?>">
</head>
<body class="login-body">
<main class="login-card">
  <img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt="Café LIF">
  <h1>Glemt adgangskode</h1>
  <?php if ($submitted): ?>
    <div class="alert alert--success">Hvis e-mailadressen findes som en aktiv administratorkonto, er der sendt et link. Linket virker én gang og udløber efter 30 minutter.</div>
    <p class="muted">Tjek også mappen Uønsket mail. Af sikkerhedshensyn oplyser siden ikke, om en bestemt e-mail findes.</p>
  <?php else: ?>
    <p>Skriv e-mailadressen, der hører til administratorkontoen.</p>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>E-mailadresse
        <input type="email" name="email" autocomplete="email" required autofocus>
      </label>
      <button class="button button--primary" type="submit">Send sikkert nulstillingslink</button>
    </form>
  <?php endif; ?>
  <p><a href="<?= h(base_url('/admin/login.php')) ?>">Tilbage til login</a></p>
</main>
</body>
</html>

