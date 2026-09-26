<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$type = $_GET['type'] ?? 'order';
$ref = trim((string)($_GET['ref'] ?? ''));
$phone = trim((string)($_GET['phone'] ?? ''));
$isOrder = $type === 'order';
?><!doctype html>
<html lang="da">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PBD598DN');</script>
<!-- End Google Tag Manager -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tak · Café LIF</title>
  <link rel="stylesheet" href="<?= h(base_url('/style.css')) ?>">
  <style>
    body{min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#f8f1e8,#fffdf9);padding:24px}
    .thanks-card{max-width:760px;background:#fff;border:1px solid rgba(12,11,10,.10);border-radius:28px;padding:clamp(24px,5vw,48px);box-shadow:0 24px 80px rgba(42,25,16,.14);text-align:center}
    .thanks-icon{width:64px;height:64px;border-radius:22px;background:rgba(200,103,58,.13);color:var(--clay);display:grid;place-items:center;margin:0 auto 18px;font-size:1.5rem}
    .thanks-card h1{font-family:var(--serif);font-weight:400;font-size:clamp(2.35rem,6vw,4.45rem);line-height:.96;margin:0 0 16px;color:var(--ink)}
    .thanks-card p{color:var(--mist);line-height:1.6}.ref-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:22px auto}.ref-box{padding:16px;border-radius:18px;background:rgba(200,103,58,.10);border:1px solid rgba(200,103,58,.20);color:var(--ink)!important;font-weight:800;text-align:left}.ref-box span{display:block;text-transform:uppercase;letter-spacing:.09em;font-size:.68rem;color:var(--mist);margin-bottom:4px}.ref-box strong{font-size:1.1rem}.status-help{margin-top:10px;padding:18px;border-radius:20px;background:#fffaf4;border:1px solid rgba(12,11,10,.08);color:var(--ink)!important}.thanks-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}.thanks-actions .btn{text-decoration:none}.thanks-credit{margin-top:22px!important;font-size:.78rem;color:var(--mist)!important}.thanks-credit a{font-weight:800;color:var(--ink)}@media(max-width:640px){.ref-grid{grid-template-columns:1fr}.thanks-card{text-align:left}.thanks-card h1{text-align:left}.thanks-icon{margin-left:0}}
  </style>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PBD598DN"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
  <main class="thanks-card">
    <div class="thanks-icon">✓</div>
    <h1><?= $isOrder ? 'Tak for din bestilling' : 'Tak for din bookingforespørgsel' ?></h1>
    <?php if ($isOrder): ?>
      <p>Din bestilling er sendt direkte til Café LIF. Vi kontakter dig hurtigst muligt og bekræfter aftalen.</p>
      <div class="ref-grid">
        <?php if ($ref !== ''): ?><p class="ref-box"><span>Reference</span><strong><?= h($ref) ?></strong></p><?php endif; ?>
        <?php if ($phone !== ''): ?><p class="ref-box"><span>Statusopslag</span><strong><?= h($phone) ?></strong></p><?php endif; ?>
      </div>
      <p class="status-help">Du kan følge status på forsiden ved at trykke <strong>Tjek ordre</strong> og indtaste det mobilnummer, du brugte ved bestillingen. Har du flere bestillinger på samme mobilnummer, vises de seneste hver for sig.</p>
    <?php else: ?>
      <?php if ($ref !== ''): ?><p class="ref-box"><span>Reference</span><strong><?= h($ref) ?></strong></p><?php endif; ?>
      <p><?= h(setting('booking_notice', 'Vi kontakter dig hurtigst muligt.')) ?></p>
    <?php endif; ?>
    <div class="thanks-actions">
      <a class="btn btn--clay" href="<?= h(base_url('/?ordrestatus=1')) ?>">Tjek ordrestatus</a>
      <a class="btn btn--outline" href="<?= h(base_url('/')) ?>">Til forsiden</a>
      <a class="btn btn--outline" href="tel:<?= h(preg_replace('/\s+/', '', (string)setting('phone', '+4561657108'))) ?>">Ring til os</a>
    </div>
  </main>
</body>
</html>
