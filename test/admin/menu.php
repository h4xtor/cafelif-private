<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

function cafelif_admin_weekday_order(string $title): int
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($title), 'UTF-8') : strtolower(trim($title));
    foreach (['mandag' => 1, 'tirsdag' => 2, 'onsdag' => 3, 'torsdag' => 4, 'fredag' => 5, 'lørdag' => 6, 'søndag' => 7] as $day => $order) {
        if (preg_match('/(?<![\p{L}])' . preg_quote($day, '/') . '(?![\p{L}])/u', $normalized) === 1) return $order;
    }
    return 99;
}

function cafelif_admin_size_base_key(string $value): string
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
    $normalized = preg_replace('/(?:\s*[-–—]?\s*)(?:xl|alm\.?|almindelig)\s*$/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
    return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
}

function cafelif_admin_is_legacy_xl_row(array $item): bool
{
    $slug = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['slug'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['slug'] ?? '')));
    $title = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['title'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['title'] ?? '')));
    return str_ends_with($slug, '-xl') || preg_match('/(?:^|[\s\-–—])xl\s*$/u', $title) === 1;
}

function cafelif_legacy_xl_duplicate_ids(array $items): array
{
    $combinedSizeKeys = [];
    foreach ($items as $item) {
        if ((float)($item['xl_price'] ?? 0) <= 0) continue;
        $categoryPrefix = (int)($item['category_id'] ?? 0) . ':';
        $slugKey = cafelif_admin_size_base_key((string)($item['slug'] ?? ''));
        $titleKey = cafelif_admin_size_base_key((string)($item['title'] ?? ''));
        if ($slugKey !== '') $combinedSizeKeys[$categoryPrefix . 'slug:' . $slugKey] = true;
        if ($titleKey !== '') $combinedSizeKeys[$categoryPrefix . 'title:' . $titleKey] = true;
    }

    $duplicates = [];
    foreach ($items as $item) {
        if (!cafelif_admin_is_legacy_xl_row($item) || (float)($item['xl_price'] ?? 0) > 0) continue;
        $categoryPrefix = (int)($item['category_id'] ?? 0) . ':';
        $slugKey = cafelif_admin_size_base_key((string)($item['slug'] ?? ''));
        $titleKey = cafelif_admin_size_base_key((string)($item['title'] ?? ''));
        if (isset($combinedSizeKeys[$categoryPrefix . 'slug:' . $slugKey]) || isset($combinedSizeKeys[$categoryPrefix . 'title:' . $titleKey])) {
            $duplicates[] = (int)$item['id'];
        }
    }
    return $duplicates;
}

function cafelif_is_meeting_item(array $item): bool
{
    $haystack = trim((string)($item['slug'] ?? '') . ' ' . (string)($item['title'] ?? ''));
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, 'mødeforplejning') || str_contains($haystack, 'moedeforplejning');
}

function cafelif_ordered_category_items(int $categoryId, string $categorySlug): array
{
    $stmt = db()->prepare('SELECT id,category_id,title,slug,xl_price,sort_order FROM menu_items WHERE category_id=?');
    $stmt->execute([$categoryId]);
    $rows = $stmt->fetchAll();
    $legacyDuplicateIds = array_fill_keys(cafelif_legacy_xl_duplicate_ids($rows), true);
    $rows = array_values(array_filter(
        $rows,
        static fn(array $row): bool => !isset($legacyDuplicateIds[(int)$row['id']]) && !cafelif_is_meeting_item($row)
    ));
    $hasManualOrder = false;
    foreach ($rows as $row) {
        if ((int)$row['sort_order'] > 0) {
            $hasManualOrder = true;
            break;
        }
    }

    usort($rows, static function (array $a, array $b) use ($categorySlug, $hasManualOrder): int {
        if ($categorySlug === 'dagens-ret' && !$hasManualOrder) {
            $weekdayCompare = cafelif_admin_weekday_order((string)$a['title']) <=> cafelif_admin_weekday_order((string)$b['title']);
            if ($weekdayCompare !== 0) return $weekdayCompare;
        }
        $sortCompare = (int)$a['sort_order'] <=> (int)$b['sort_order'];
        if ($sortCompare !== 0) return $sortCompare;
        $titleCompare = strnatcasecmp((string)$a['title'], (string)$b['title']);
        return $titleCompare !== 0 ? $titleCompare : (int)$a['id'] <=> (int)$b['id'];
    });
    return $rows;
}

function cafelif_move_menu_item(int $id, int $direction): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT m.category_id,c.slug category_slug FROM menu_items m JOIN menu_categories c ON c.id=m.category_id WHERE m.id=?');
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    if (!$current) {
        flash('error', 'Retten findes ikke længere.');
        return;
    }

    $orderedRows = cafelif_ordered_category_items((int)$current['category_id'], (string)$current['category_slug']);
    $ids = array_map('intval', array_column($orderedRows, 'id'));
    $index = array_search($id, $ids, true);
    if ($index === false) return;

    $target = $index + $direction;
    if ($target < 0 || $target >= count($ids)) {
        flash('success', $direction < 0 ? 'Retten står allerede øverst i kategorien.' : 'Retten står allerede nederst i kategorien.');
        return;
    }

    [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE menu_items SET sort_order=? WHERE id=?');
        foreach ($ids as $position => $menuId) {
            $update->execute([($position + 1) * 10, $menuId]);
        }
        $pdo->commit();
        flash('success', 'Rækkefølgen er gemt og bruges nu på hjemmesiden.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($id > 0 && $action === 'toggle') {
        db()->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE id=?')->execute([$id]);
        flash('success', 'Synligheden er ændret. Hvis retten er synlig, vises den på hjemmesiden.');
    } elseif ($id > 0 && $action === 'delete') {
        db()->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]);
        flash('success', 'Retten er slettet.');
    } elseif ($id > 0 && $action === 'move_up') {
        cafelif_move_menu_item($id, -1);
    } elseif ($id > 0 && $action === 'move_down') {
        cafelif_move_menu_item($id, 1);
    }
    redirect('/admin/menu.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['category'] ?? 0);
$sql = 'SELECT m.*, c.name category_name, c.slug category_slug, c.sort_order category_sort FROM menu_items m JOIN menu_categories c ON c.id=m.category_id WHERE 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (m.title LIKE ? OR m.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat > 0) {
    $sql .= ' AND m.category_id=?';
    $params[] = $cat;
}
$sql .= ' ORDER BY c.sort_order,m.sort_order,m.title';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$items = array_values(array_filter($items, static fn(array $item): bool => !cafelif_is_meeting_item($item)));

$legacyRows = db()->query('SELECT id,category_id,title,slug,xl_price FROM menu_items')->fetchAll();
$legacyDuplicateIds = array_fill_keys(cafelif_legacy_xl_duplicate_ids($legacyRows), true);

$manualOrderByCategory = [];
foreach ($items as $item) {
    if (isset($legacyDuplicateIds[(int)$item['id']])) continue;
    $categoryId = (int)$item['category_id'];
    $manualOrderByCategory[$categoryId] = ($manualOrderByCategory[$categoryId] ?? false) || (int)$item['sort_order'] > 0;
}
usort($items, static function (array $a, array $b) use ($manualOrderByCategory, $legacyDuplicateIds): int {
    $categoryCompare = (int)$a['category_sort'] <=> (int)$b['category_sort'];
    if ($categoryCompare !== 0) return $categoryCompare;
    $categoryId = (int)$a['category_id'];
    $legacyCompare = (int)isset($legacyDuplicateIds[(int)$a['id']]) <=> (int)isset($legacyDuplicateIds[(int)$b['id']]);
    if ($legacyCompare !== 0) return $legacyCompare;
    if ((string)$a['category_slug'] === 'dagens-ret' && !($manualOrderByCategory[$categoryId] ?? false)) {
        $weekdayCompare = cafelif_admin_weekday_order((string)$a['title']) <=> cafelif_admin_weekday_order((string)$b['title']);
        if ($weekdayCompare !== 0) return $weekdayCompare;
    }
    $sortCompare = (int)$a['sort_order'] <=> (int)$b['sort_order'];
    if ($sortCompare !== 0) return $sortCompare;
    return strnatcasecmp((string)$a['title'], (string)$b['title']);
});

$categories = db()->query('SELECT * FROM menu_categories ORDER BY sort_order,name')->fetchAll();
$orderPositions = [];
foreach ($categories as $category) {
    $orderedRows = cafelif_ordered_category_items((int)$category['id'], (string)$category['slug']);
    $count = count($orderedRows);
    foreach ($orderedRows as $position => $row) {
        $orderPositions[(int)$row['id']] = ['position' => $position + 1, 'count' => $count];
    }
}

admin_layout_start('Menu & priser', 'menu');
?>
<div class="page-intro">
  <p class="admin-kicker">Menu & priser</p>
  <h2>Her kan du ændre retter, priser og rækkefølge</h2>
  <p>Her kan du rette en eksisterende ret, oprette en ny ret eller skjule en ret. Når du gemmer, bliver ændringen vist på hjemmesiden.</p>
  <p><strong>Rækkefølge:</strong> Pil op og pil ned ved hver ret kan bruges til at vælge rækkefølgen. Funktionen virker i alle kategorier – også under Dagens ret. Første flytning gemmer den valgte rækkefølge.</p>
  <p><strong>Mødeforplejning:</strong> Fanen <a href="<?= h(base_url('/admin/meeting.php')) ?>"><strong>Mødeforplejning</strong></a> samler sektionens tekster, muligheder, knapper og prisoplysninger ét sted.</p>
  <?php if ($legacyDuplicateIds): ?><p><strong>Gamle XL-rækker:</strong> De særskilte XL-retter fra den tidligere løsning er markeret nedenfor og skjules automatisk for kunderne. Når du har kontrolleret, at Alm. og XL virker på hovedretten, kan du slette de gamle XL-rækker.</p><?php endif; ?>
</div>
<div class="toolbar toolbar--sticky"><form class="toolbar__search" method="get"><input type="search" name="q" value="<?= h($q) ?>" placeholder="Søg efter en ret"><select name="category"><option value="0">Alle kategorier</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select><button class="button button--soft">Søg</button></form><div class="actions"><a class="button button--soft" href="<?= h(base_url('/admin/categories.php')) ?>"><i class="fa-solid fa-layer-group"></i> Kategorier</a><a class="button button--primary" href="<?= h(base_url('/admin/menu-edit.php')) ?>"><i class="fa-solid fa-plus"></i> Opret ny ret</a></div></div>
<div class="admin-card-list">
<?php foreach ($items as $item): $isLegacyXl = isset($legacyDuplicateIds[(int)$item['id']]); $isMeetingItem = cafelif_is_meeting_item($item); $position = $orderPositions[(int)$item['id']] ?? ['position' => 0, 'count' => 0]; ?>
  <article class="admin-item-card <?= $item['is_available'] ? '' : 'is-muted' ?>">
    <div class="admin-item-card__media">
      <?php if ($item['image_path']): ?><img src="<?= h(base_url($item['image_path'])) ?>" alt=""><?php else: ?><span><i class="fa-solid fa-image"></i></span><?php endif; ?>
    </div>
    <div class="admin-item-card__body">
      <div class="admin-item-card__head"><div><h3><?= h($item['title']) ?></h3><p><?= h($item['category_name']) ?></p></div><strong><?= $item['xl_price'] ? 'Alm. ' . h(money($item['price'])) . '<br><small>XL ' . h(money($item['xl_price'])) . '</small>' : h(money($item['price'])) ?></strong></div>
      <?php if ($item['description']): ?><p class="admin-item-card__desc"><?= h(mb_strimwidth((string)$item['description'], 0, 140, '…')) ?></p><?php endif; ?>
      <div class="admin-item-card__meta">
        <span class="badge badge--<?= $item['is_available'] ? 'active' : 'inactive' ?>"><?= $item['is_available'] ? 'Synlig på hjemmesiden' : 'Skjult på hjemmesiden' ?></span>
        <?php if ($isLegacyXl): ?>
          <span class="badge badge--legacy"><i class="fa-solid fa-triangle-exclamation"></i> Gammel separat XL-række – skjules for kunderne</span>
        <?php elseif ($isMeetingItem): ?>
          <span class="badge badge--meeting"><i class="fa-solid fa-briefcase"></i> Selvstændig fane – redigeres under Mødeforplejning</span>
        <?php else: ?>
          <span class="badge badge--order"><i class="fa-solid fa-arrow-down-1-9"></i> Placering <?= (int)$position['position'] ?> af <?= (int)$position['count'] ?></span>
        <?php endif; ?>
        <?php if ($item['xl_price']): ?><span class="badge">Kunden kan vælge Alm. eller XL</span><?php endif; ?>
        <?php if ((string)$item['category_slug'] === 'dagens-ret' && !(int)$item['sort_order']): ?><span class="badge">Ugedagsrækkefølge indtil du flytter retten</span><?php endif; ?>
        <?php if ($item['badge']): ?><span class="badge"><?= h($item['badge']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="admin-item-card__actions">
      <?php if (cafelif_is_meeting_item($item)): ?>
      <a class="button button--primary" href="<?= h(base_url('/admin/meeting.php')) ?>"><i class="fa-solid fa-briefcase"></i> Ret fanen</a>
      <a class="button button--soft" href="<?= h(base_url('/admin/menu-edit.php?id=' . $item['id'])) ?>"><i class="fa-solid fa-sliders"></i> Avanceret</a>
      <?php else: ?>
      <a class="button button--primary" href="<?= h(base_url('/admin/menu-edit.php?id=' . $item['id'])) ?>"><i class="fa-solid fa-pen"></i> Ret</a>
      <?php endif; ?>
      <?php if (!$isLegacyXl && !$isMeetingItem): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="move_up"><button class="button button--soft button--order" title="Flyt retten én placering op" aria-label="Flyt <?= h($item['title']) ?> op" <?= (int)$position['position'] <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-arrow-up"></i><span>Op</span></button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="move_down"><button class="button button--soft button--order" title="Flyt retten én placering ned" aria-label="Flyt <?= h($item['title']) ?> ned" <?= (int)$position['position'] >= (int)$position['count'] ? 'disabled' : '' ?>><i class="fa-solid fa-arrow-down"></i><span>Ned</span></button></form>
      <?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="button button--soft" title="Skift synlighed"><i class="fa-solid <?= $item['is_available'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $item['is_available'] ? 'Skjul' : 'Vis' ?></button></form>
      <form method="post" data-confirm="Slet retten permanent?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button--danger"><i class="fa-solid fa-trash"></i></button></form>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$items): ?><div class="panel__empty">Der er ingen retter, der matcher søgningen.</div><?php endif; ?>
</div>
<?php admin_layout_end(); ?>
