<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/front.php';

$menuGroups = front_menu_data();
$phone = (string)setting('phone', '+45 61 65 71 08');
$email = (string)setting('email', 'cafelif@lystrup-if.dk');
$meetingEnabled = (string)setting('meeting_enabled', '1') !== '0';
$meetingTitle = trim((string)setting('meeting_title', 'Mødeforplejning')) ?: 'Mødeforplejning';
?>
<!doctype html>
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
  <title>Menukort · Café LIF</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap">
  <link rel="stylesheet" href="<?= h(base_url('/assets/css/site.css?v=25')) ?>">
</head>
<body>
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PBD598DN" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->

  <div class="topline">Se menu og priser · kontakt os for bestilling</div>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?= h(base_url('/')) ?>"><img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt=""><span>Café LIF</span></a>
      <nav class="main-nav">
        <a href="<?= h(base_url('/')) ?>">Forside</a>
        <?php if ($meetingEnabled): ?><a href="<?= h(base_url('/#moedeforplejning')) ?>"><?= h($meetingTitle) ?></a><?php endif; ?>
        <a href="<?= h(base_url('/#kontakt')) ?>">Kontakt</a>
      </nav>
      <div class="header-actions"><a class="btn btn--red" href="<?= h(front_tel_href($phone)) ?>">Ring</a></div>
    </div>
  </header>

  <main class="menu-page">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">Menukort</span>
        <h1 class="section-title">Retter og priser</h1>
        <p class="section-desc">Ring eller skriv, hvis du vil bestille eller høre mere.</p>
      </div>

      <div class="menu-layout">
        <nav class="category-nav" aria-label="Menukategorier">
          <?php foreach ($menuGroups as $group): $cat = $group['category']; ?>
            <a href="#cat-<?= (int)($cat['id'] ?? 0) ?>"><?= h($cat['name'] ?? 'Menu') ?></a>
          <?php endforeach; ?>
        </nav>

        <div class="menu-sections">
          <?php foreach ($menuGroups as $group): $cat = $group['category']; ?>
          <section class="menu-section" id="cat-<?= (int)($cat['id'] ?? 0) ?>">
            <header class="menu-section__head">
              <h2><?= h($cat['name'] ?? 'Menu') ?></h2>
              <p><?= h(front_group_nav_description($group)) ?></p>
            </header>
            <?php foreach ($group['items'] as $item): ?>
            <article class="menu-row <?= !empty($item['image_path']) ? 'menu-row--with-image' : '' ?>" data-menu-track data-id="<?= (int)$item['id'] ?>" data-track-url="<?= h(base_url('/actions/track.php')) ?>">
              <?php if (!empty($item['image_path'])): ?>
              <div class="menu-row__image"><img src="<?= h(base_url($item['image_path'])) ?>" alt="<?= h($item['title']) ?>" loading="lazy" width="120" height="120"></div>
              <?php endif; ?>
              <div>
                <h3><?= h($item['title']) ?></h3>
                <?php if (!empty($item['description'])): ?><p><?= h($item['description']) ?></p><?php endif; ?>
                <?php if (!empty($item['badge'])): ?><span class="tag"><?= h($item['badge']) ?></span><?php endif; ?>
              </div>
              <div class="menu-row__action"><span class="price"><?= front_money_label($item) ?></span></div>
            </article>
            <?php endforeach; ?>
          </section>
          <?php endforeach; ?>
        </div>

        <aside class="cart-box" id="kontakt">
          <h2 style="margin-top:0">Kontakt</h2>
          <p>Ring eller send en mail, så aftaler vi mad, afhentning og detaljer.</p>
          <p><a class="btn btn--red" href="<?= h(front_tel_href($phone)) ?>" style="width:100%;margin-top:12px">Ring til os</a></p>
          <p><a class="btn btn--soft" href="mailto:<?= h($email) ?>" style="width:100%;margin-top:8px">Send mail</a></p>
        </aside>
      </div>
    </div>
  </main>
  <script src="<?= h(base_url('/assets/js/site.js?v=25')) ?>"></script>
</body>
</html>
