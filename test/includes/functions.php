<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    global $config;
    return rtrim((string)$config['app']['base_url'], '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function money(float|int|string|null $amount): string
{
    if ($amount === null || $amount === '') return 'Efter aftale';
    return number_format((float)$amount, 0, ',', '.') . ' kr.';
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value !== false ? $value : $default;
    } catch (Throwable) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function client_session_id(): string
{
    if (empty($_COOKIE['cafelif_sid']) || !preg_match('/^[a-f0-9]{32}$/', $_COOKIE['cafelif_sid'])) {
        $sid = bin2hex(random_bytes(16));
        setcookie('cafelif_sid', $sid, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['cafelif_sid'] = $sid;
    }
    return $_COOKIE['cafelif_sid'];
}

function privacy_hash(string $value): string
{
    global $config;
    return hash_hmac('sha256', $value, (string)$config['app']['app_key']);
}

function log_event(string $type, ?int $itemId = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO analytics_events (event_type, item_id, page, session_id, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            mb_substr($type, 0, 60),
            $itemId,
            mb_substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', 0, 190),
            client_session_id(),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable) {
        // Analytics må aldrig blokere siden.
    }
}

function log_page_visit(): void
{
    try {
        $sid = client_session_id();
        $page = mb_substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', 0, 190);
        $stmt = db()->prepare('INSERT INTO site_visits (session_id, page, ip_hash, user_agent_hash, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([
            $sid,
            $page,
            privacy_hash($_SERVER['REMOTE_ADDR'] ?? ''),
            privacy_hash($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
    } catch (Throwable) {
    }
}

function rate_limit(string $key, int $max, int $seconds): bool
{
    $now = time();
    $_SESSION['rate_limits'][$key] = array_values(array_filter(
        $_SESSION['rate_limits'][$key] ?? [],
        fn(int $t) => $t > $now - $seconds
    ));
    if (count($_SESSION['rate_limits'][$key]) >= $max) return false;
    $_SESSION['rate_limits'][$key][] = $now;
    return true;
}

function admin_layout_start(string $title, string $active = 'dashboard'): void
{
    $admin = current_admin();
    $hiddenAdmin = ($admin['role'] ?? '') === 'superadmin';
    $displayName = $hiddenAdmin ? 'Administrator' : (string)($admin['name'] ?? 'Admin');
    $displayEmail = $hiddenAdmin ? '' : (string)($admin['email'] ?? '');
    $flashes = pull_flashes();
    $nav = [
        'dashboard' => ['Start', 'fa-house', '/admin/index.php'],
        'menu' => ['Menu & priser', 'fa-utensils', '/admin/menu.php'],
        'meeting' => ['Mødeforplejning', 'fa-briefcase', '/admin/meeting.php'],
        'orders' => ['Bestillinger', 'fa-bag-shopping', '/admin/orders.php'],
        'posts' => ['Madgalleri', 'fa-images', '/admin/posts.php'],
        'settings' => ['Tekst & info', 'fa-pen-to-square', '/admin/settings.php'],
    ];
    ?>
<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · Café LIF Admin</title><link rel="icon" href="<?= h(base_url('/assets/img/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= h(base_url('/assets/css/admin.css?v=24')) ?>"></head><body>
<div class="admin-shell"><aside class="admin-sidebar" id="admin-sidebar">
<a class="admin-brand" href="<?= h(base_url('/admin/index.php')) ?>"><img src="<?= h(base_url('/assets/img/cafeliflogo.jpg')) ?>" alt=""><span><small>Café LIF</small><strong>Administration</strong></span></a>
<nav class="admin-nav">
<?php foreach ($nav as $key => [$label,$icon,$url]): ?><a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= h(base_url($url)) ?>"><i class="fa-solid <?= h($icon) ?>"></i><span><?= h($label) ?></span></a><?php endforeach; ?>
</nav><div class="admin-sidebar__footer"><a href="<?= h(base_url('/')) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Se hjemmesiden</a><a href="<?= h(base_url('/admin/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket"></i> Log ud</a></div></aside>
<div class="admin-main"><header class="admin-topbar"><button class="admin-menu-toggle" type="button" data-admin-toggle aria-label="Åbn menu"><i class="fa-solid fa-bars"></i></button><div><p class="admin-kicker">Café LIF</p><h1><?= h($title) ?></h1></div><div class="admin-user"><div><strong><?= h($displayName) ?></strong><?php if ($displayEmail !== ''): ?><span><?= h($displayEmail) ?></span><?php endif; ?></div><span class="admin-avatar"><?= h(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></span></div></header>
<main class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= h($f['type']) ?>"><?= h($f['message']) ?></div><?php endforeach; ?>
<?php
}

function admin_layout_end(): void
{
    ?><footer class="admin-footer">Café LIF · adminområde</footer></main></div></div><div class="admin-overlay" data-admin-overlay></div><script src="<?= h(base_url('/assets/js/admin.js')) ?>"></script></body></html><?php
}
