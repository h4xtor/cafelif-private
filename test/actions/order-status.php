<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function order_status_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (substr($digits, 0, 4) === '0045') {
        $digits = substr($digits, 4);
    }
    if (strlen($digits) > 8 && substr($digits, 0, 2) === '45') {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) > 8) {
        $digits = substr($digits, -8);
    }
    return $digits;
}

function order_status_json(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function order_status_datetime(?string $value): string
{
    if (!$value) return '-';
    $ts = strtotime($value);
    return $ts ? date('d.m.Y H:i', $ts) : $value;
}

function order_status_date(?string $value): string
{
    if (!$value) return '';
    $ts = strtotime($value);
    return $ts ? date('d.m.Y', $ts) : $value;
}

function order_status_time(?string $value): string
{
    return $value ? substr($value, 0, 5) : '';
}

function order_status_has_column(PDO $pdo, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM orders LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable) {
        return false;
    }
}

function order_status_ensure_archive_columns(PDO $pdo): void
{
    try {
        if (!order_status_has_column($pdo, 'archived_at')) {
            $pdo->exec('ALTER TABLE orders ADD archived_at DATETIME NULL AFTER updated_at');
        }
        if (!order_status_has_column($pdo, 'deleted_at')) {
            $pdo->exec('ALTER TABLE orders ADD deleted_at DATETIME NULL AFTER archived_at');
        }
    } catch (Throwable $e) {
        error_log('Café LIF ordrestatus archive columns: ' . $e->getMessage());
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    order_status_json(['ok' => false, 'message' => 'Skriv dit mobilnummer for at se ordrestatus.']);
}

if (!rate_limit('order_status', 40, 600)) {
    order_status_json(['ok' => false, 'message' => 'Der er lavet for mange opslag lige nu. Prøv igen om lidt.'], 429);
}

$phoneRaw = trim((string)($_POST['phone'] ?? ''));
$key = order_status_normalize_phone($phoneRaw);

if (strlen($key) !== 8) {
    order_status_json(['ok' => false, 'message' => 'Skriv det 8-cifrede mobilnummer, du brugte ved bestillingen.']);
}

$labels = [
    'new' => 'Ny',
    'confirmed' => 'Bekræftet',
    'preparing' => 'Tilberedes',
    'ready' => 'Klar til afhentning',
    'completed' => 'Afsluttet',
    'cancelled' => 'Annulleret',
];

$notes = [
    'new' => 'Bestillingen er modtaget. Café LIF følger op hurtigst muligt.',
    'confirmed' => 'Bestillingen er bekræftet.',
    'preparing' => 'Bestillingen er under tilberedning.',
    'ready' => 'Bestillingen er klar til afhentning.',
    'completed' => 'Bestillingen er afsluttet. Tak for din bestilling.',
    'cancelled' => 'Bestillingen er markeret som annulleret. Kontakt Café LIF hvis du er i tvivl.',
];

try {
    $pdo = db();
    order_status_ensure_archive_columns($pdo);

    // Søg bredt i databasen, men godkend kun præcise telefonmatch efter normalisering.
    // Det gør statusopslag robust overfor +45, mellemrum, bindestreger og samme nummer brugt flere gange.
    $last4 = substr($key, -4);
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE deleted_at IS NULL AND (phone LIKE ? OR order_number LIKE ?) ORDER BY created_at DESC LIMIT 200");
    $stmt->execute(['%' . $last4 . '%', '%' . $key . '%']);

    $orders = [];
    foreach ($stmt->fetchAll() as $row) {
        $phone = (string)($row['phone'] ?? '');
        $orderNumber = (string)($row['order_number'] ?? '');
        if (order_status_normalize_phone($phone) !== $key && order_status_normalize_phone($orderNumber) !== $key) {
            continue;
        }

        $status = (string)($row['status'] ?? 'new');
        $desiredDate = order_status_date((string)($row['desired_date'] ?? ''));
        $desiredTime = order_status_time((string)($row['desired_time'] ?? ''));
        $desired = trim($desiredDate . ($desiredTime ? ' kl. ' . $desiredTime : ''));
        $updatedAt = (string)($row['updated_at'] ?? $row['created_at'] ?? '');

        $orders[] = [
            'reference' => (string)($row['order_number'] ?? ''),
            'status' => $status,
            'status_label' => $labels[$status] ?? ucfirst($status),
            'status_note' => $notes[$status] ?? 'Status er opdateret på bestillingen.',
            'created_at' => order_status_datetime((string)($row['created_at'] ?? '')),
            'updated_at' => order_status_datetime($updatedAt),
            'desired' => $desired ?: 'Ikke angivet',
            'total' => money($row['total_estimate'] ?? null),
        ];
    }

    if (!$orders) {
        order_status_json([
            'ok' => false,
            'message' => 'Vi fandt ingen bestilling på det mobilnummer endnu. Tjek at nummeret er det samme som ved bestillingen.'
        ]);
    }

    order_status_json([
        'ok' => true,
        'phone' => $key,
        'orders' => array_slice($orders, 0, 5),
    ]);
} catch (Throwable $e) {
    error_log('Café LIF ordrestatus fejl: ' . $e->getMessage());
    order_status_json(['ok' => false, 'message' => 'Ordrestatus kunne ikke hentes lige nu. Prøv igen om lidt.'], 500);
}
