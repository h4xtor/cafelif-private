<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/front.php';
require_admin();

function cafelif_menu_next_sort_order(int $categoryId): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM menu_items WHERE category_id=?');
    $stmt->execute([$categoryId]);
    return max(10, (int)$stmt->fetchColumn() + 10);
}

function cafelif_menu_is_meeting_item(array $item): bool
{
    $haystack = trim((string)($item['slug'] ?? '') . ' ' . (string)($item['title'] ?? ''));
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, 'mødeforplejning') || str_contains($haystack, 'moedeforplejning');
}

$id = (int)($_GET['id'] ?? 0);
$item = ['category_id' => '', 'title' => '', 'slug' => '', 'description' => '', 'price' => '', 'xl_price' => '', 'price_suffix' => '', 'image_path' => '', 'badge' => '', 'allergens' => '', 'is_featured' => 0, 'is_available' => 1, 'sort_order' => ''];
if ($id) {
    $s = db()->prepare('SELECT * FROM menu_items WHERE id=?');
    $s->execute([$id]);
    $item = $s->fetch();
    if (!$item) {
        http_response_code(404);
        exit('Retten findes ikke.');
    }
}
$categories = db()->query('SELECT * FROM menu_categories WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') throw new RuntimeException('Retten skal have et navn.');
        $slug = trim((string)($_POST['slug'] ?? ''));
        if ($slug === '') $slug = mb_strtolower(preg_replace('/[^a-zA-Z0-9æøåÆØÅ]+/u', '-', $title));
        $slug = trim($slug, '-');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) throw new RuntimeException('Vælg en kategori.');

        $image = $item['image_path'] ?: null;
        $uploaded = store_uploaded_image($_FILES['image'] ?? [], 'menu');
        if ($uploaded) $image = $uploaded;

        $priceRaw = trim((string)($_POST['price'] ?? ''));
        $xlPriceRaw = trim((string)($_POST['xl_price'] ?? ''));
        $price = $priceRaw !== '' ? (float)str_replace(',', '.', $priceRaw) : null;
        $xlPrice = $xlPriceRaw !== '' ? (float)str_replace(',', '.', $xlPriceRaw) : null;
        if ($xlPrice !== null && $xlPrice > 0 && ($price === null || $price <= 0)) throw new RuntimeException('En almindelig pris er nødvendig, når retten også har en XL-pris.');

        $sortOrderRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sortOrder = $sortOrderRaw !== '' ? (int)$sortOrderRaw : ($id ? (int)$item['sort_order'] : cafelif_menu_next_sort_order($categoryId));
        $data = [$categoryId, $title, $slug, trim((string)($_POST['description'] ?? '')), $price, $xlPrice, trim((string)($_POST['price_suffix'] ?? '')), $image, trim((string)($_POST['badge'] ?? '')), trim((string)($_POST['allergens'] ?? '')), isset($_POST['is_featured']) ? 1 : 0, isset($_POST['is_available']) ? 1 : 0, $sortOrder];
        if ($id) {
            $sql = 'UPDATE menu_items SET category_id=?,title=?,slug=?,description=?,price=?,xl_price=?,price_suffix=?,image_path=?,badge=?,allergens=?,is_featured=?,is_available=?,sort_order=? WHERE id=?';
            $data[] = $id;
        } else {
            $sql = 'INSERT INTO menu_items(category_id,title,slug,description,price,xl_price,price_suffix,image_path,badge,allergens,is_featured,is_available,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)';
        }
        db()->prepare($sql)->execute($data);

        $meetingCandidate = [
            'title' => $title,
            'slug' => $slug,
            'description' => trim((string)($_POST['description'] ?? '')),
            'price_suffix' => trim((string)($_POST['price_suffix'] ?? '')),
        ];
        if (front_is_meeting_item($meetingCandidate)) {
            $meetingParts = front_menu_item_text_parts($meetingCandidate);
            set_setting('meeting_title', $title);
            if ($meetingCandidate['description'] !== '') set_setting('meeting_intro', (string)$meetingParts['intro']);
            if (preg_match('/^[*•-]\s*.+$/mu', $meetingCandidate['description']) === 1) {
                set_setting('meeting_points', implode("\n", (array)$meetingParts['points']));
            }
            if ($meetingCandidate['price_suffix'] !== '') set_setting('meeting_price_text', $meetingCandidate['price_suffix']);
            set_setting('meeting_order_price', number_format((float)($price ?? 0), 2, '.', ''));
            set_setting('meeting_order_enabled', isset($_POST['is_available']) ? '1' : '0');
        }

        flash('success', $id ? 'Retten er gemt og opdateret på hjemmesiden.' : 'Den nye ret er oprettet nederst i den valgte kategori.');
        redirect('/admin/menu.php');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Der findes allerede en ret med samme tekniske navn. Prøv at ændre titlen lidt.' : $e->getMessage();
        $item = array_merge($item, $_POST);
    }
}
$isMeetingItem = cafelif_menu_is_meeting_item($item);
admin_layout_start($id ? 'Ret en ret' : 'Opret ny ret', 'menu');
?>
<?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
<div class="page-intro"><p class="admin-kicker"><?= $id ? 'Ret ret' : 'Ny ret' ?></p><h2><?= $id ? 'Her kan du ændre retten' : 'Her kan du oprette en ny ret' ?></h2><p>Navn, kategori, beskrivelse, priser og eventuelt billede kan tilpasses her. Gemte ændringer bliver automatisk vist på hjemmesiden.</p></div>
<form method="post" enctype="multipart/form-data" class="form-card"><?= csrf_field() ?>
  <div class="form-grid">
    <?php if ($isMeetingItem): ?><div class="field field--full"><div class="alert alert--info"><strong>Mødeforplejning:</strong> Den selvstændige sektion har sin egen side under <a href="<?= h(base_url('/admin/meeting.php')) ?>"><strong>Mødeforplejning</strong></a> i menuen. Navn, beskrivelse og pristekst synkroniseres automatisk mellem siderne, mens de øvrige tekster og knapper findes på den dedikerede side.</div></div><?php endif; ?>
    <div class="field"><label>Retnavn *</label><input name="title" value="<?= h($item['title']) ?>" required placeholder="F.eks. Boller i karry"><small>Det navn der vises på hjemmesiden.</small></div>
    <div class="field"><label>Kategori *</label><select name="category_id" required><option value="">Vælg kategori</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$item['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select><small>Kategorien bestemmer, hvor retten bliver vist i menuen.</small></div>
    <div class="field field--full"><label>Beskrivelse</label><textarea name="description" placeholder="Kort beskrivelse af retten"><?= h($item['description']) ?></textarea><small><?= $isMeetingItem ? 'Introduktionen kan stå først, efterfulgt af hvert tilbud på en ny linje med * foran.' : 'En kort og appetitlig tekst fungerer bedst.' ?></small></div>
    <div class="field field--full"><div class="alert alert--info"><strong>Størrelser:</strong> Retten oprettes kun én gang. En almindelig pris og en XL-pris giver kunden mulighed for selv at vælge størrelse. XL-feltet kan stå tomt, når retten kun findes i én størrelse.</div></div>
    <div class="field"><label>Almindelig størrelse</label><input type="number" step="0.01" min="0" name="price" value="<?= h($item['price']) ?>" placeholder="65"><small>Prisen kunden betaler for almindelig størrelse.</small></div>
    <div class="field"><label>XL-størrelse</label><input type="number" step="0.01" min="0" name="xl_price" value="<?= h($item['xl_price']) ?>" placeholder="80"><small>Når du udfylder denne pris, vises både Alm. og XL på hjemmesiden.</small></div>
    <div class="field"><label>Tekst efter pris</label><input name="price_suffix" value="<?= h($item['price_suffix']) ?>" placeholder="f.eks. pr. kuvert"><small><?= $isMeetingItem ? 'Her kan du f.eks. skrive “85 kr. – 350 kr.” til fanen.' : 'Bruges fx til tapas eller catering.' ?></small></div>
    <div class="field"><label>Lille mærkat</label><input name="badge" value="<?= h($item['badge']) ?>" placeholder="Hjemmelavet / Populær"><small>Vises som en lille label under retten.</small></div>
    <div class="field"><label>Allergener eller noter</label><input name="allergens" value="<?= h($item['allergens']) ?>" placeholder="Gluten, mælk, vegetar"><small>Kan stå tomt.</small></div>
    <div class="field"><label>Sortering</label><input type="number" name="sort_order" value="<?= h($item['sort_order']) ?>"><small>Feltet kan normalt stå uændret. Pilene på oversigten kan bruges til at flytte retten sikkert op eller ned.</small></div>
    <div class="field field--full"><label>Billede af retten</label><?php if ($item['image_path']): ?><img class="image-preview" data-image-preview src="<?= h(base_url($item['image_path']) . '?v=' . time()) ?>" alt="Nuværende billede af retten"><?php else: ?><img class="image-preview" data-image-preview alt="" style="display:none"><?php endif; ?><input data-image-input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>Et nyt billede bliver gemt på retten sammen med de øvrige ændringer og vist på hjemmesiden. JPG, PNG og WebP virker bedst.</small></div>
    <div class="field field--full"><div class="check-row"><label class="check"><input type="checkbox" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>> Vis retten på hjemmesiden</label><label class="check"><input type="checkbox" name="is_featured" <?= $item['is_featured'] ? 'checked' : '' ?>> Fremhæv retten</label></div></div>
    <details class="field field--full advanced-box"><summary>Avanceret felt</summary><label>Teknisk navn/slug</label><input name="slug" value="<?= h($item['slug']) ?>" placeholder="oprettes automatisk"><small>Feltet oprettes automatisk og kan normalt stå uændret.</small></details>
  </div>
  <div class="form-actions sticky-save"><a class="button button--soft" href="<?= h(base_url('/admin/menu.php')) ?>">Annuller</a><button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Gem ret</button></div>
</form>
<?php admin_layout_end(); ?>
