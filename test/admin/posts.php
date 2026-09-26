<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php'; require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$id=(int)($_POST['id']??0);
  if(($_POST['action']??'')==='toggle') db()->prepare('UPDATE food_posts SET is_active=1-is_active WHERE id=?')->execute([$id]);
  elseif(($_POST['action']??'')==='delete') db()->prepare('DELETE FROM food_posts WHERE id=?')->execute([$id]);
  flash('success','Madgalleriet er opdateret.');redirect('/admin/posts.php');
}
$posts=db()->query('SELECT p.*,m.title menu_title FROM food_posts p LEFT JOIN menu_items m ON m.id=p.menu_item_id ORDER BY p.sort_order,p.published_at DESC,p.id DESC')->fetchAll();
admin_layout_start('Madgalleri','posts');
?>
<div class="page-intro"><p class="admin-kicker">Madgalleri</p><h2>Her kan du skifte billeder på hjemmesiden</h2><p>Tilføj nye madbilleder, ret teksten til et billede eller skjul et billede, hvis det ikke skal vises i galleriet lige nu.</p></div>
<div class="toolbar"><p class="muted">Aktive billeder/opslag kan vises i madgalleriet på forsiden.</p><a class="button button--primary" href="<?= h(base_url('/admin/post-edit.php')) ?>"><i class="fa-solid fa-plus"></i> Tilføj billede</a></div>
<div class="gallery-admin-grid">
<?php foreach($posts as $p): ?>
  <article class="gallery-admin-card <?= $p['is_active'] ? '' : 'is-muted' ?>">
    <div class="gallery-admin-card__img"><?php if($p['image_path']): ?><img src="<?= h(base_url($p['image_path'])) ?>" alt=""><?php else: ?><span><i class="fa-solid fa-image"></i></span><?php endif; ?></div>
    <div class="gallery-admin-card__body"><h3><?= h($p['title']) ?></h3><p><?= h(mb_strimwidth((string)$p['body'],0,110,'…')) ?></p><div class="admin-item-card__meta"><?php if($p['price_text']): ?><span class="badge"><?= h($p['price_text']) ?></span><?php endif; ?><span class="badge badge--<?= $p['is_active']?'active':'inactive' ?>"><?= $p['is_active']?'Vises':'Skjult' ?></span></div></div>
    <div class="admin-item-card__actions"><a class="button button--primary" href="<?= h(base_url('/admin/post-edit.php?id='.$p['id'])) ?>"><i class="fa-solid fa-pen"></i> Ret</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="button button--soft"><i class="fa-solid <?= $p['is_active']?'fa-eye-slash':'fa-eye' ?>"></i> <?= $p['is_active']?'Skjul':'Vis' ?></button></form><form method="post" data-confirm="Slet billedet/opslaget?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button--danger"><i class="fa-solid fa-trash"></i></button></form></div>
  </article>
<?php endforeach; ?>
<?php if(!$posts): ?><div class="panel__empty">Der er endnu ingen billeder i madgalleriet.</div><?php endif; ?>
</div>
<?php admin_layout_end(); ?>
