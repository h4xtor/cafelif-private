<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php'; require_admin();
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=$_POST['action']??'save';$id=(int)($_POST['id']??0);
 try{
  if($action==='delete'){
   $s=db()->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id=?');$s->execute([$id]);
   if((int)$s->fetchColumn()>0)throw new RuntimeException('Kategorien kan ikke slettes, mens den indeholder retter.');
   db()->prepare('DELETE FROM menu_categories WHERE id=?')->execute([$id]);flash('success','Kategorien er slettet.');
  }else{
   $name=trim((string)($_POST['name']??''));if($name==='')throw new RuntimeException('Navn er påkrævet.');
   $slug=trim((string)($_POST['slug']??''));if($slug==='')$slug=trim(mb_strtolower(preg_replace('/[^a-zA-Z0-9æøåÆØÅ]+/u','-',$name)),'-');
   $data=[$name,$slug,trim((string)($_POST['description']??'')),(int)($_POST['sort_order']??0),isset($_POST['is_active'])?1:0];
   if($id){$data[]=$id;db()->prepare('UPDATE menu_categories SET name=?,slug=?,description=?,sort_order=?,is_active=? WHERE id=?')->execute($data);}else{db()->prepare('INSERT INTO menu_categories(name,slug,description,sort_order,is_active) VALUES(?,?,?,?,?)')->execute($data);}flash('success','Kategorien er gemt.');
  }
  redirect('/admin/categories.php');
 }catch(Throwable $e){$error=$e instanceof PDOException?'Sluggen er allerede i brug.':$e->getMessage();}
}
$editId=(int)($_GET['id']??0);$edit=['id'=>0,'name'=>'','slug'=>'','description'=>'','sort_order'=>0,'is_active'=>1];if($editId){$s=db()->prepare('SELECT * FROM menu_categories WHERE id=?');$s->execute([$editId]);$edit=$s->fetch()?:$edit;}
$cats=db()->query('SELECT c.*,(SELECT COUNT(*) FROM menu_items m WHERE m.category_id=c.id) item_count FROM menu_categories c ORDER BY sort_order,name')->fetchAll();
admin_layout_start('Menukategorier','menu');
?><?php if($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?><section class="content-grid"><div class="panel"><div class="panel__header"><h2>Kategorier</h2><a class="button button--soft button--small" href="<?= h(base_url('/admin/menu.php')) ?>">Til menukort</a></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Kategori</th><th>Retter</th><th>Sortering</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($cats as $c): ?><tr><td><strong><?= h($c['name']) ?></strong><br><small><?= h($c['description']) ?></small></td><td><?= (int)$c['item_count'] ?></td><td><?= (int)$c['sort_order'] ?></td><td><span class="badge badge--<?= $c['is_active']?'active':'inactive' ?>"><?= $c['is_active']?'Aktiv':'Skjult' ?></span></td><td><div class="actions"><a class="button button--soft button--small" href="?id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-pen"></i></a><form method="post" data-confirm="Slet kategorien?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="button button--danger button--small"><i class="fa-solid fa-trash"></i></button></form></div></td></tr><?php endforeach; ?></tbody></table></div></div><form method="post" class="form-card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><h2 style="margin-top:0"><?= $edit['id']?'Rediger kategori':'Opret kategori' ?></h2><div class="stack"><label>Navn<input name="name" value="<?= h($edit['name']) ?>" required></label><label>Beskrivelse<textarea name="description"><?= h($edit['description']) ?></textarea></label><label>Slug<input name="slug" value="<?= h($edit['slug']) ?>"></label><label>Sortering<input type="number" name="sort_order" value="<?= h($edit['sort_order']) ?>"></label><label class="check"><input type="checkbox" name="is_active" <?= $edit['is_active']?'checked':'' ?>> Aktiv</label><button class="button button--primary">Gem kategori</button></div></form></section><?php admin_layout_end(); ?>
