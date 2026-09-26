<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin(true);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$error = null;
$admin = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $repeat = (string)($_POST['password_repeat'] ?? '');

    if (mb_strlen($password) < 12) {
        $error = 'Adgangskoden skal være mindst 12 tegn.';
    } elseif ($password !== $repeat) {
        $error = 'Adgangskoderne er ikke ens.';
    } else {
        $stmt = db()->prepare(
            'UPDATE admins
             SET password_hash = ?,
                 must_change_password = 0,
                 password_changed_at = NOW(),
                 session_version = session_version + 1
             WHERE id = ?'
        );
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$admin['id']]);

        $_SESSION['admin_session_version'] = (int)$admin['session_version'] + 1;
        record_admin_security_event('admin_password_changed', (int)$admin['id']);
        flash('success', 'Din adgangskode er ændret. Andre gamle login-sessioner er nu afbrudt.');
        redirect('/admin/index.php');
    }
}
?><!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ny adgangskode · Café LIF</title>
  <link rel="stylesheet" href="<?= h(base_url('/assets/css/admin.css')) ?>">
</head>
<body class="login-body">
<main class="login-card">
  <img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt="Café LIF">
  <h1>Vælg ny adgangskode</h1>
  <p>Adgangskoden skal være mindst 12 tegn. Den ændres kun for din egen konto.</p>
  <?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Ny adgangskode
      <input type="password" name="password" minlength="12" autocomplete="new-password" required>
    </label>
    <label>Gentag adgangskode
      <input type="password" name="password_repeat" minlength="12" autocomplete="new-password" required>
    </label>
    <button class="button button--primary" type="submit">Gem adgangskode</button>
  </form>
</main>
</body>
</html>

