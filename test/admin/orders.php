<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
require_once dirname(__DIR__) . '/includes/order_confirmation.php';

$labels = [
    'new' => 'Ny',
    'confirmed' => 'Bekræftet',
    'preparing' => 'Tilberedes',
    'ready' => 'Klar til afhentning',
    'completed' => 'Afsluttet',
    'cancelled' => 'Annulleret',
];

$statusHelp = [
    'new' => 'Ring kunden op og bekræft bestillingen, dato og tidspunkt.',
    'confirmed' => 'Aftalen er bekræftet med kunden.',
    'preparing' => 'Bestillingen er i gang i køkkenet.',
    'ready' => 'Bestillingen er klar til afhentning.',
    'completed' => 'Bestillingen er færdig og ligger i historik.',
    'cancelled' => 'Bestillingen er annulleret og ligger i historik.',
];

$statusTone = [
    'new' => 'red',
    'confirmed' => 'green',
    'preparing' => 'orange',
    'ready' => 'blue',
    'completed' => 'green',
    'cancelled' => 'muted',
];

function cafelif_orders_has_column(PDO $pdo, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM orders LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable) {
        return false;
    }
}

function cafelif_orders_ensure_archive_columns(): void
{
    $pdo = db();
    try {
        if (!cafelif_orders_has_column($pdo, 'archived_at')) {
            $pdo->exec('ALTER TABLE orders ADD archived_at DATETIME NULL AFTER updated_at');
        }
        if (!cafelif_orders_has_column($pdo, 'deleted_at')) {
            $pdo->exec('ALTER TABLE orders ADD deleted_at DATETIME NULL AFTER archived_at');
        }
        try {
            $pdo->exec('ALTER TABLE orders ADD INDEX idx_orders_archive (deleted_at, archived_at, status)');
        } catch (Throwable) {
            // Index findes allerede eller webhotellet afviser ekstra index. Siden virker stadig.
        }
    } catch (Throwable $e) {
        error_log('Café LIF archive columns: ' . $e->getMessage());
    }
}

function order_phone_href(?string $phone): string
{
    return preg_replace('/[^0-9+]/', '', (string)$phone) ?: '';
}

function order_short_date(?string $date): string
{
    if (!$date) return 'Ikke angivet';
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
}

function order_short_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : 'Ikke angivet';
}

function order_created_fmt(?string $date): string
{
    if (!$date) return 'Ukendt tidspunkt';
    $ts = strtotime($date);
    return $ts ? date('d.m.Y H:i', $ts) : $date;
}

function order_datetime_line(?string $date, ?string $time): string
{
    return order_short_date($date) . ' kl. ' . order_short_time($time);
}

function order_status_label(array $labels, string $status): string
{
    return $labels[$status] ?? $status;
}

cafelif_orders_ensure_archive_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['order_action'] ?? 'status');
    $returnView = (string)($_POST['return_view'] ?? 'active');
    if (!in_array($returnView, ['active', 'history', 'trash'], true)) $returnView = 'active';

    if ($id > 0) {
        if ($action === 'send_confirmation') {
            $pdo = db();
            $lock = 'cafelif-order-mail-' . $id;
            $lockHeld = false;
            try {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $stmt->execute([$lock]);
                $lockHeld = (int)$stmt->fetchColumn() === 1;
                if (!$lockHeld) throw new RuntimeException('En mail er allerede ved at blive sendt. Vent et øjeblik.');
                $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND deleted_at IS NULL');
                $stmt->execute([$id]);
                $order = $stmt->fetch();
                if (!$order) throw new RuntimeException('Ordren findes ikke eller ligger i papirkurven.');
                if (empty($order['confirmation_email_requested']) && empty($_POST['confirm_manual_email'])) {
                    throw new RuntimeException('Bekræft først, at kunden har bedt om en mail efter bestillingen.');
                }
                if (!empty($order['customer_mail_sent_at']) && strtotime($order['customer_mail_sent_at']) > time() - 60) {
                    throw new RuntimeException('Der er lige sendt en mail. Vent et minut før gensendelse.');
                }
                $result = cafelif_send_order_confirmation($pdo, $id, $config, true);
                if (!$result['customer_mail_sent']) throw new RuntimeException('Mailen kunne ikke sendes. Kontroller mailopsætningen og prøv igen.');
                flash('success', $result['test_mode'] ? 'Ordrebekræftelsen er sendt til den konfigurerede testmodtager.' : 'Ordrebekræftelsen er sendt til kunden.');
            } catch (Throwable $e) {
                flash('error', $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Mailen kunne ikke sendes. Prøv igen senere.');
            } finally {
                if ($lockHeld) $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
            }
            redirect('/admin/orders.php?view=' . urlencode($returnView) . '&id=' . $id . '#ordre-mail');
        }
        if ($action === 'status') {
            $status = (string)($_POST['status'] ?? 'new');
            if (array_key_exists($status, $labels)) {
                if (in_array($status, ['completed', 'cancelled'], true)) {
                    db()->prepare('UPDATE orders SET status=?, archived_at=COALESCE(archived_at, NOW()), deleted_at=NULL WHERE id=?')->execute([$status, $id]);
                    flash('success', 'Status er gemt. Ordren er flyttet til afsluttet historik, så aktive bestillinger holdes ryddelige.');
                    redirect('/admin/orders.php?view=history&id=' . $id . '#ordre-detaljer');
                }
                db()->prepare('UPDATE orders SET status=?, archived_at=NULL, deleted_at=NULL WHERE id=?')->execute([$status, $id]);
                flash('success', 'Status er gemt. Kunden kan nu se den nye status med sit mobilnummer på hjemmesiden.');
                redirect('/admin/orders.php?view=active&id=' . $id . '#ordre-detaljer');
            }
        }

        if ($action === 'archive') {
            db()->prepare('UPDATE orders SET archived_at=NOW(), deleted_at=NULL, status=IF(status IN (\'completed\',\'cancelled\'), status, \'completed\') WHERE id=?')->execute([$id]);
            flash('success', 'Ordren er flyttet til afsluttet historik.');
            redirect('/admin/orders.php?view=history&id=' . $id . '#ordre-detaljer');
        }

        if ($action === 'trash') {
            db()->prepare('UPDATE orders SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            flash('success', 'Ordren er flyttet til papirkurven. Du kan gendanne den igen derfra.');
            redirect('/admin/orders.php?view=trash');
        }

        if ($action === 'restore') {
            db()->prepare('UPDATE orders SET deleted_at=NULL, archived_at=NULL, status=IF(status IN (\'completed\',\'cancelled\'), \'new\', status) WHERE id=?')->execute([$id]);
            flash('success', 'Ordren er gendannet og ligger igen under aktive bestillinger.');
            redirect('/admin/orders.php?view=active&id=' . $id . '#ordre-detaljer');
        }

        if ($action === 'delete_permanent') {
            db()->prepare('DELETE FROM orders WHERE id=? AND deleted_at IS NOT NULL')->execute([$id]);
            flash('success', 'Ordren er slettet permanent fra papirkurven.');
            redirect('/admin/orders.php?view=trash');
        }
    }

    redirect('/admin/orders.php?view=' . urlencode($returnView));
}

$view = (string)($_GET['view'] ?? 'active');
if (!in_array($view, ['active', 'history', 'trash'], true)) $view = 'active';

$statusFilter = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = [];

if ($view === 'trash') {
    $where[] = 'deleted_at IS NOT NULL';
} elseif ($view === 'history') {
    $where[] = 'deleted_at IS NULL';
    $where[] = '(archived_at IS NOT NULL OR status IN (\'completed\',\'cancelled\'))';
} else {
    $where[] = 'deleted_at IS NULL';
    $where[] = 'archived_at IS NULL';
    $where[] = "status NOT IN ('completed','cancelled')";
}

if ($statusFilter !== '' && array_key_exists($statusFilter, $labels)) {
    $where[] = 'status=?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR phone LIKE ? OR email LIKE ? OR order_number LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$sql = 'SELECT * FROM orders';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$s = db()->prepare($sql);
$s->execute($params);
$orders = $s->fetchAll();

$id = (int)($_GET['id'] ?? 0);
if (!$id && $orders) {
    $id = (int)$orders[0]['id'];
}

$detail = null;
$detailItems = [];
if ($id) {
    $s = db()->prepare('SELECT * FROM orders WHERE id=?');
    $s->execute([$id]);
    $detail = $s->fetch();
    if ($detail) {
        $s = db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $s->execute([$id]);
        $detailItems = $s->fetchAll();
    }
}

$countOpen = (int)db()->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND archived_at IS NULL AND status NOT IN ('completed','cancelled')")->fetchColumn();
$countHistory = (int)db()->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND (archived_at IS NOT NULL OR status IN ('completed','cancelled'))")->fetchColumn();
$countTrash = (int)db()->query('SELECT COUNT(*) FROM orders WHERE deleted_at IS NOT NULL')->fetchColumn();
$countNew = (int)db()->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND archived_at IS NULL AND status='new'")->fetchColumn();
$countToday = (int)db()->query('SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND DATE(created_at)=CURDATE()')->fetchColumn();
$countReady = (int)db()->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND archived_at IS NULL AND status='ready'")->fetchColumn();

$viewTitle = [
    'active' => 'Aktive bestillinger',
    'history' => 'Afsluttet historik',
    'trash' => 'Papirkurv',
][$view];

admin_layout_start('Bestillinger', 'orders');
?>
<section class="orders-v14-stats" aria-label="Overblik over bestillinger">
  <article><span>Nye</span><strong><?= $countNew ?></strong><small>venter på opfølgning</small></article>
  <article><span>Aktive</span><strong><?= $countOpen ?></strong><small>åbne bestillinger</small></article>
  <article><span>Klar</span><strong><?= $countReady ?></strong><small>klar til afhentning</small></article>
  <article><span>I dag</span><strong><?= $countToday ?></strong><small>modtaget i dag</small></article>
</section>

<section class="orders-v14-toolbar">
  <nav class="orders-v14-tabs" aria-label="Ordrevisning">
    <a class="<?= $view === 'active' ? 'is-active' : '' ?>" href="<?= h(base_url('/admin/orders.php?view=active')) ?>"><i class="fa-solid fa-inbox"></i><span>Aktive</span><strong><?= $countOpen ?></strong></a>
    <a class="<?= $view === 'history' ? 'is-active' : '' ?>" href="<?= h(base_url('/admin/orders.php?view=history')) ?>"><i class="fa-solid fa-box-archive"></i><span>Historik</span><strong><?= $countHistory ?></strong></a>
    <a class="<?= $view === 'trash' ? 'is-active' : '' ?>" href="<?= h(base_url('/admin/orders.php?view=trash')) ?>"><i class="fa-solid fa-trash"></i><span>Papirkurv</span><strong><?= $countTrash ?></strong></a>
  </nav>

  <form method="get" class="orders-v14-filter">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <label>
      <span>Søg</span>
      <input name="q" value="<?= h($q) ?>" placeholder="Ordrenummer, navn, mobil eller e-mail">
    </label>
    <label>
      <span>Status</span>
      <select name="status">
        <option value="">Alle statusser</option>
        <?php foreach ($labels as $k => $v): ?><option value="<?= h($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="button button--primary"><i class="fa-solid fa-filter"></i> Filtrer</button>
    <a class="button button--soft" href="<?= h(base_url('/admin/orders.php?view=' . $view)) ?>"><i class="fa-solid fa-rotate-right"></i> Nulstil</a>
  </form>
</section>

<section class="orders-v14-board">
  <aside class="orders-v14-list" aria-label="<?= h($viewTitle) ?>">
    <div class="orders-v14-list-head">
      <div>
        <h2><?= h($viewTitle) ?></h2>
        <?php if ($view === 'active'): ?><p>Kun bestillinger der stadig kræver opfølgning.</p><?php endif; ?>
        <?php if ($view === 'history'): ?><p>Færdige og annullerede bestillinger ligger her.</p><?php endif; ?>
        <?php if ($view === 'trash'): ?><p>Bestillinger der er ryddet væk. De kan gendannes.</p><?php endif; ?>
      </div>
      <a class="button button--soft" href="<?= h(base_url('/admin/orders.php?view=' . $view)) ?>"><i class="fa-solid fa-rotate-right"></i></a>
    </div>

    <?php if (!$orders): ?>
      <div class="orders-v14-empty">Der er ingen bestillinger i denne visning.</div>
    <?php else: ?>
      <div class="orders-v14-card-list">
      <?php foreach ($orders as $o): ?>
        <?php $status = (string)$o['status']; $tone = $statusTone[$status] ?? 'muted'; $isSelected = (int)$o['id'] === $id; ?>
        <a class="orders-v14-order-card <?= $isSelected ? 'is-selected' : '' ?> tone-<?= h($tone) ?>" href="<?= h(base_url('/admin/orders.php?view=' . $view . '&id=' . $o['id'] . ($statusFilter ? '&status=' . urlencode($statusFilter) : '') . ($q !== '' ? '&q=' . urlencode($q) : '') . '#ordre-detaljer')) ?>">
          <div class="orders-v14-order-top">
            <strong><?= h($o['customer_name']) ?></strong>
            <span class="orders-v14-badge orders-v14-badge--<?= h($status) ?>"><?= h(order_status_label($labels, $status)) ?></span>
          </div>
          <div class="orders-v14-order-meta">
            <span><i class="fa-solid fa-phone"></i> <?= h($o['phone']) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= h(order_created_fmt($o['created_at'])) ?></span>
            <span><i class="fa-solid fa-calendar-check"></i> <?= h(order_datetime_line($o['desired_date'], $o['desired_time'])) ?></span>
            <span><i class="fa-solid fa-envelope"></i> Mailbekræftelse ønsket: <strong style="font-weight:800"><?= !empty($o['confirmation_email_requested']) ? 'Ja' : 'Nej' ?></strong></span>
          </div>
          <div class="orders-v14-order-bottom">
            <span>Ref. <?= h($o['order_number'] ?: $o['phone']) ?></span>
            <strong><?= h(money($o['total_estimate'])) ?></strong>
          </div>
        </a>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>

  <section class="orders-v14-detail" id="ordre-detaljer">
    <?php if (!$detail): ?>
      <div class="orders-v14-no-detail">
        <i class="fa-solid fa-bag-shopping"></i>
        <h2>Vælg en bestilling</h2>
        <p>Tryk på en bestilling til venstre for at se kundeinfo, retter og status.</p>
      </div>
    <?php else: ?>
      <?php $detailStatus = (string)$detail['status']; $tone = $statusTone[$detailStatus] ?? 'muted'; $isDeleted = !empty($detail['deleted_at']); $isArchived = !$isDeleted && (!empty($detail['archived_at']) || in_array($detailStatus, ['completed', 'cancelled'], true)); ?>
      <article class="orders-v14-detail-card tone-<?= h($tone) ?> <?= $isDeleted ? 'is-trash' : '' ?>">
        <header class="orders-v14-detail-header">
          <div>
            <p class="admin-kicker">Reference</p>
            <h2><?= h($detail['order_number'] ?: $detail['phone']) ?></h2>
            <strong><?= h($detail['customer_name']) ?></strong>
            <p><?= h($statusHelp[$detailStatus] ?? 'Følg op på bestillingen og gem status, når noget ændrer sig.') ?></p>
          </div>
          <div class="orders-v14-total">
            <span class="orders-v14-badge orders-v14-badge--<?= h($detailStatus) ?>"><?= h(order_status_label($labels, $detailStatus)) ?></span>
            <strong><?= h(money($detail['total_estimate'])) ?></strong>
            <small>estimeret total</small>
          </div>
        </header>

        <?php if ($isDeleted): ?><div class="orders-v14-note"><i class="fa-solid fa-trash"></i> Denne ordre ligger i papirkurven og vises ikke som aktiv ordre.</div><?php endif; ?>
        <?php if ($isArchived): ?><div class="orders-v14-note"><i class="fa-solid fa-box-archive"></i> Denne ordre ligger i afsluttet historik og fylder ikke i aktive bestillinger.</div><?php endif; ?>

        <div class="orders-v14-info-grid">
          <article><span>Kunde</span><strong><?= h($detail['customer_name']) ?></strong><small><?= h($detail['email'] ?: 'E-mail ikke angivet') ?></small></article>
          <article>
            <span>Mailbekræftelse ønsket</span>
            <strong style="font-weight:800"><?= !empty($detail['confirmation_email_requested']) ? 'Ja' : 'Nej' ?></strong>
            <small>
              <?php if (empty($detail['confirmation_email_requested'])): ?>
                Kunden fravalgte mailbekræftelse
              <?php elseif (!empty($detail['customer_mail_sent_at'])): ?>
                <?= str_contains((string)parse_url(base_url('/'), PHP_URL_PATH), '/test/') ? 'Testmail sendt til Café LIFs testmodtager' : 'Ordrebekræftelse sendt' ?>
              <?php elseif (!empty($detail['customer_mail_error'])): ?>
                Afsendelse mislykkedes
              <?php else: ?>
                Afsendelse ikke bekræftet
              <?php endif; ?>
            </small>
          </article>
          <article><span>Reference</span><strong><?= h($detail['order_number'] ?: '-') ?></strong><small>unik reference til denne bestilling</small></article>
          <article><span>Telefon</span><strong><a href="tel:<?= h(order_phone_href($detail['phone'])) ?>"><?= h($detail['phone']) ?></a></strong><small>kunden bruger mobilnummeret til Tjek ordre</small></article>
          <article><span>Ønsket afhentning</span><strong><?= h(order_datetime_line($detail['desired_date'], $detail['desired_time'])) ?></strong><small>bekræft tidspunktet med kunden</small></article>
          <article><span>Modtaget</span><strong><?= h(order_created_fmt($detail['created_at'])) ?></strong><small>sendt via hjemmesiden</small></article>
        </div>

        <div class="orders-v14-detail-grid">
          <section class="orders-v14-section" id="ordre-mail">
            <div class="orders-v14-section-head"><h3>Ordrebekræftelse på mail</h3></div>
            <p>Modtager: <strong><?= h($detail['email'] ?: 'E-mail ikke angivet') ?></strong></p>
            <?php if (str_contains((string)parse_url(base_url('/'), PHP_URL_PATH), '/test/')): ?>
              <p class="orders-v14-hint">Test: mailen sendes kun til den konfigurerede testmodtager.</p>
            <?php endif; ?>
            <?php if (!empty($detail['customer_mail_sent_at'])): ?>
              <p>Senest afsendt: <strong><?= h(order_created_fmt($detail['customer_mail_sent_at'])) ?></strong></p>
            <?php endif; ?>
            <?php if (!empty($detail['customer_mail_error'])): ?><p role="alert">Seneste forsøg mislykkedes. Du kan prøve igen her.</p><?php endif; ?>
            <?php if (!$isDeleted && filter_var($detail['email'], FILTER_VALIDATE_EMAIL)): ?>
              <form method="post" class="orders-v14-status-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
                <input type="hidden" name="order_action" value="send_confirmation">
                <input type="hidden" name="return_view" value="<?= h($view) ?>">
                <?php if (empty($detail['confirmation_email_requested'])): ?>
                  <label><input type="checkbox" name="confirm_manual_email" value="1" required> Kunden har efterfølgende bedt om en bekræftelse på mail</label>
                <?php endif; ?>
                <button class="button button--primary" type="submit"><i class="fa-solid fa-envelope"></i> <?= !empty($detail['customer_mail_sent_at']) ? 'Gensend bekræftelse til kunden på mail' : 'Send bekræftelse til kunden på mail' ?></button>
              </form>
              <p class="orders-v14-hint">Mailen indeholder den gemte ordre med retter, antal, priser og ønsket afhentning.</p>
            <?php else: ?><p class="orders-v14-hint"><?= $isDeleted ? 'Gendan ordren for at sende en bekræftelse.' : 'En gyldig e-mailadresse er nødvendig for at sende bekræftelsen.' ?></p><?php endif; ?>
          </section>
          <section class="orders-v14-section orders-v14-products">
            <div class="orders-v14-section-head"><h3>Valgte retter</h3><span><?= count($detailItems) ?> linje(r)</span></div>
            <?php if (!$detailItems): ?>
              <p class="muted">Der er ikke registreret retter på ordren.</p>
            <?php else: ?>
              <div class="orders-v14-products-list">
                <?php foreach ($detailItems as $it): ?>
                  <div class="orders-v14-product-row">
                    <div><strong><?= (int)$it['quantity'] ?> × <?= h($it['item_name']) ?></strong><small><?= h(money($it['unit_price'])) ?> pr. stk.</small></div>
                    <strong><?= h(money(((float)$it['unit_price']) * ((int)$it['quantity']))) ?></strong>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="orders-v14-total-line"><span>Estimeret total</span><strong><?= h(money($detail['total_estimate'])) ?></strong></div>
          </section>

          <section class="orders-v14-section orders-v14-status-panel">
            <div class="orders-v14-section-head"><h3>Status</h3><span>kunden kan se den</span></div>
            <?php if (!$isDeleted): ?>
              <form method="post" class="orders-v14-status-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
                <input type="hidden" name="order_action" value="status">
                <input type="hidden" name="return_view" value="<?= h($view) ?>">
                <label>
                  <span>Vælg status</span>
                  <select name="status">
                    <?php foreach ($labels as $k => $v): ?><option value="<?= h($k) ?>" <?= $detailStatus === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
                  </select>
                </label>
                <button class="button button--primary"><i class="fa-solid fa-floppy-disk"></i> Gem status</button>
              </form>
              <p class="orders-v14-hint">Når du trykker Gem status, kan kunden trykke <strong>Tjek ordre</strong> på hjemmesiden og se den nye status med sit mobilnummer.</p>
            <?php else: ?>
              <p class="orders-v14-hint">Ordren ligger i papirkurven. Gendan den først, hvis du vil ændre status.</p>
            <?php endif; ?>
          </section>

          <section class="orders-v14-section orders-v14-message">
            <div class="orders-v14-section-head"><h3>Besked fra kunden</h3><span>ønsker/allergi</span></div>
            <?php if ($detail['message']): ?>
              <p><?= nl2br(h($detail['message'])) ?></p>
            <?php else: ?>
              <p class="muted">Kunden har ikke skrevet en besked.</p>
            <?php endif; ?>
          </section>

          <section class="orders-v14-section orders-v14-actions">
            <div class="orders-v14-section-head"><h3>Handlinger</h3><span>ring eller ryd op</span></div>
            <div class="orders-v14-action-grid">
              <a class="button button--dark" href="tel:<?= h(order_phone_href($detail['phone'])) ?>"><i class="fa-solid fa-phone"></i> Ring kunden op</a>
              <?php if ($isDeleted): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="order_action" value="restore"><button class="button button--primary" type="submit"><i class="fa-solid fa-rotate-left"></i> Gendan ordre</button></form>
                <form method="post" onsubmit="return confirm('Vil du slette ordren permanent? Dette kan ikke fortrydes.');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="order_action" value="delete_permanent"><button class="button button--danger" type="submit"><i class="fa-solid fa-trash-can"></i> Slet permanent</button></form>
              <?php else: ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="order_action" value="archive"><button class="button button--soft" type="submit"><i class="fa-solid fa-box-archive"></i> Flyt til historik</button></form>
                <form method="post" onsubmit="return confirm('Vil du flytte ordren til papirkurven? Du kan gendanne den igen bagefter.');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="order_action" value="trash"><button class="button button--danger" type="submit"><i class="fa-solid fa-trash"></i> Flyt til papirkurv</button></form>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </article>
    <?php endif; ?>
  </section>
</section>
<script>
(function(){
  const intervalMs = 30000;
  const board = document.querySelector('.orders-v14-board');
  if (!board) return;
  let userIsEditing = false;
  const markEditing = event => {
    if (event.target.closest('form')) userIsEditing = true;
  };
  document.addEventListener('input', markEditing, true);
  document.addEventListener('change', markEditing, true);
  document.addEventListener('submit', () => { userIsEditing = true; }, true);

  const badge = document.createElement('div');
  badge.className = 'orders-v15-autorefresh';
  badge.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Opdaterer automatisk hvert 30. sekund';
  const toolbar = document.querySelector('.orders-v14-toolbar');
  if (toolbar) toolbar.insertAdjacentElement('afterend', badge);

  setInterval(() => {
    if (document.hidden || userIsEditing) return;
    const active = document.activeElement;
    if (active && ['INPUT','SELECT','TEXTAREA','BUTTON'].includes(active.tagName)) return;
    const url = new URL(window.location.href);
    url.searchParams.set('auto', String(Date.now()));
    window.location.replace(url.toString());
  }, intervalMs);
})();
</script>
<?php admin_layout_end(); ?>
