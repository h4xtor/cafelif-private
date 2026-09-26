<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$admin = current_admin();
function scalar(string $sql, array $params=[]): int { $s=db()->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
$stats = [
    'items' => scalar('SELECT COUNT(*) FROM menu_items WHERE is_available=1'),
    'gallery' => scalar("SELECT COUNT(*) FROM food_posts WHERE is_active=1 AND image_path IS NOT NULL AND image_path<>''"),
    'orders_new' => scalar("SELECT COUNT(*) FROM orders WHERE status IN ('new','confirmed','preparing','ready')"),
    'orders_today' => scalar('SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()'),
];
$lastItems = db()->query('SELECT id,title,price,is_available,updated_at FROM menu_items ORDER BY updated_at DESC,id DESC LIMIT 4')->fetchAll();
$lastOrders = db()->query('SELECT id,order_number,customer_name,phone,total_estimate,status,created_at FROM orders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$labels=['new'=>'Ny','confirmed'=>'Bekræftet','preparing'=>'Tilberedes','ready'=>'Klar','completed'=>'Afsluttet','cancelled'=>'Annulleret'];
admin_layout_start('Start','dashboard');
?>
<section class="dashboard-hero dashboard-hero--simple">
  <div class="welcome-card welcome-card--eva">
    <p class="admin-kicker">Hej <?= h(($admin['role'] ?? '') === 'superadmin' ? 'Administrator' : (string)($admin['name'] ?? 'admin')) ?> 👋</p>
    <h2>Her kan du styre hjemmesiden</h2>
    <p>Her kan du rette mad, priser, billeder, tekst, kontaktoplysninger og åbningstider. Du kan også se bestillinger, der kommer ind fra hjemmesiden, så du kan ringe kunden op og bekræfte aftalen.</p>
    <div class="welcome-actions">
      <a class="button button--primary" href="<?= h(base_url('/admin/menu.php')) ?>"><i class="fa-solid fa-utensils"></i> Ret menu og priser</a>
      <a class="button button--soft" href="<?= h(base_url('/admin/orders.php')) ?>"><i class="fa-solid fa-bag-shopping"></i> Se bestillinger</a>
      <a class="button button--soft" href="<?= h(base_url('/')) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Se hjemmesiden</a>
    </div>
  </div>
  <aside class="quick-actions quick-actions--large">
    <h3>Hvad vil du ændre?</h3>
    <div class="quick-grid quick-grid--cards">
      <a class="quick-link" href="<?= h(base_url('/admin/menu.php')) ?>"><i class="fa-solid fa-bowl-food"></i><span><strong>Menu & priser</strong><small>Ret priser, navne og beskrivelser</small></span></a>
      <a class="quick-link" href="<?= h(base_url('/admin/menu-edit.php')) ?>"><i class="fa-solid fa-plus"></i><span><strong>Ny ret</strong><small>Opret en ny ret med billede</small></span></a>
      <a class="quick-link" href="<?= h(base_url('/admin/orders.php')) ?>"><i class="fa-solid fa-bag-shopping"></i><span><strong>Bestillinger</strong><small>Se nye bestillinger og ring kunden op</small></span></a>
      <a class="quick-link" href="<?= h(base_url('/admin/posts.php')) ?>"><i class="fa-solid fa-images"></i><span><strong>Madgalleri</strong><small>Tilføj eller fjern billeder</small></span></a>
      <a class="quick-link" href="<?= h(base_url('/admin/settings.php')) ?>"><i class="fa-solid fa-pen-to-square"></i><span><strong>Tekst & info</strong><small>Ret tekst, marquee og åbningstider</small></span></a>
    </div>
  </aside>
</section>

<section class="stats-grid stats-grid--clean">
  <article class="stat-card stat-card--gold"><div class="stat-card__top"><span>Synlige retter</span><span class="stat-card__icon"><i class="fa-solid fa-utensils"></i></span></div><strong><?= $stats['items'] ?></strong><span>vises på hjemmesiden</span></article>
  <article class="stat-card stat-card--green"><div class="stat-card__top"><span>Madbilleder</span><span class="stat-card__icon"><i class="fa-solid fa-image"></i></span></div><strong><?= $stats['gallery'] ?></strong><span>aktive billeder/opslag</span></article>
  <article class="stat-card stat-card--blue"><div class="stat-card__top"><span>Åbne bestillinger</span><span class="stat-card__icon"><i class="fa-solid fa-bag-shopping"></i></span></div><strong><?= $stats['orders_new'] ?></strong><span>skal følges op på</span></article>
  <article class="stat-card"><div class="stat-card__top"><span>I dag</span><span class="stat-card__icon"><i class="fa-solid fa-clock"></i></span></div><strong><?= $stats['orders_today'] ?></strong><span>bestillinger modtaget i dag</span></article>
</section>

<div class="content-grid content-grid--help">
  <section class="panel">
    <div class="panel__header"><h2>Sådan bruger du adminområdet</h2></div>
    <div class="panel__body help-steps">
      <article><span>1</span><div><strong>Ret menu og priser</strong><p>Her kan du ændre en ret, rette prisen, skifte billede eller skjule retten, hvis den ikke skal vises lige nu.</p></div></article>
      <article><span>2</span><div><strong>Se bestillinger</strong><p>Her kan du se hvad kunden har valgt, navn, telefonnummer, besked samt dato og tidspunkt for bestillingen. Ring kunden op og bekræft aftalen.</p></div></article>
      <article><span>3</span><div><strong>Skift billeder og tekst</strong><p>Her kan du lægge nye madbilleder ind, rette teksten der kører øverst hen over skærmen og ændre åbningstider.</p></div></article>
      <article><span>4</span><div><strong>Tryk Gem</strong><p>Når du har ændret noget, skal du trykke Gem. Derefter kan du åbne hjemmesiden og se ændringen.</p></div></article>
    </div>
  </section>
  <section class="panel">
    <div class="panel__header"><h2>Seneste bestillinger</h2><a class="button button--soft button--small" href="<?= h(base_url('/admin/orders.php')) ?>">Se alle</a></div>
    <div class="panel__body mini-list">
      <?php if (!$lastOrders): ?><p class="muted">Der er endnu ingen bestillinger fra hjemmesiden.</p><?php endif; ?>
      <?php foreach ($lastOrders as $o): ?>
      <a class="mini-row" href="<?= h(base_url('/admin/orders.php?id=' . $o['id'])) ?>">
        <span class="mini-row__icon"><i class="fa-solid fa-bag-shopping"></i></span>
        <span class="mini-row__body"><strong><?= h($o['customer_name']) ?> · <?= h($o['order_number']) ?></strong><span><?= h(money($o['total_estimate'])) ?> · <?= h($labels[$o['status']] ?? $o['status']) ?> · <?= h(date('d.m H:i', strtotime($o['created_at']))) ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="panel" style="margin-top:22px">
  <div class="panel__header"><h2>Senest ændrede retter</h2></div>
  <div class="panel__body mini-list">
    <?php foreach ($lastItems as $item): ?>
    <a class="mini-row" href="<?= h(base_url('/admin/menu-edit.php?id=' . $item['id'])) ?>">
      <span class="mini-row__icon"><i class="fa-solid fa-utensils"></i></span>
      <span class="mini-row__body"><strong><?= h($item['title']) ?></strong><span><?= h(money($item['price'])) ?> · <?= $item['is_available'] ? 'synlig' : 'skjult' ?></span></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php admin_layout_end(); ?>
