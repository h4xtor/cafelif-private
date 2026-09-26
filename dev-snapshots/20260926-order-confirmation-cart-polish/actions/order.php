<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/front.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

function cafelif_order_is_ajax(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function cafelif_order_respond(array $data, int $code = 200): never
{
    if (cafelif_order_is_ajax()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!($data['ok'] ?? false)) {
        http_response_code($code >= 400 ? $code : 400);
        exit((string)($data['message'] ?? 'Bestillingen kunne ikke sendes.'));
    }

    redirect('/tak.php?type=order&ref=' . urlencode((string)($data['reference'] ?? '')) . '&phone=' . urlencode((string)($data['phone'] ?? '')));
}

function cafelif_order_fail(string $message, int $code = 400): never
{
    cafelif_order_respond(['ok' => false, 'message' => $message], $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/menu.php');

try {
    verify_csrf();
} catch (Throwable) {
    cafelif_order_fail('Siden var udløbet. Opdater siden og prøv igen.', 419);
}

if (!rate_limit('order', 8, 600)) {
    cafelif_order_fail('Der er sendt for mange bestillinger på kort tid. Vent lidt og prøv igen.', 429);
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$confirmationRequested = filter_var($_POST['confirmation_email_requested'] ?? false, FILTER_VALIDATE_BOOLEAN);
$cart = json_decode((string)($_POST['cart'] ?? '[]'), true);
if ($name === '' || $phone === '' || !is_array($cart) || !$cart) {
    cafelif_order_fail('Navn, telefon og mindst ét valg er påkrævet.');
}
if (mb_strlen($name) < 2 || mb_strlen($name) > 160) cafelif_order_fail('Skriv dit navn med 2 til 160 tegn.');
if ($confirmationRequested && $email === '') cafelif_order_fail('Skriv din e-mailadresse for at få ordrebekræftelse.');
if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) cafelif_order_fail('Skriv en gyldig e-mailadresse.');
if (count($cart) > 50) cafelif_order_fail('Bestillingen har for mange varelinjer. Kontakt Café LIF eller del den op.');

function order_phone_key(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '0045')) $digits = substr($digits, 4);
    if (strlen($digits) === 10 && str_starts_with($digits, '45')) $digits = substr($digits, 2);
    return $digits;
}

function cafelif_order_reference(): string
{
    return 'LIF-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

$phoneKey = order_phone_key($phone);
if (strlen($phoneKey) !== 8) {
    cafelif_order_fail('Skriv et gyldigt mobilnummer, så Café LIF kan bekræfte bestillingen.');
}

$desiredDate = trim((string)($_POST['date'] ?? ''));
$desiredTime = trim((string)($_POST['time'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
if ($desiredDate !== '') {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $desiredDate);
    if (!$date || $date->format('Y-m-d') !== $desiredDate) cafelif_order_fail('Skriv en gyldig ønsket dato.');
}
if ($desiredTime !== '' && !preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $desiredTime)) cafelif_order_fail('Skriv et gyldigt ønsket tidspunkt.');
if (mb_strlen($message) > 5000) cafelif_order_fail('Beskeden må højst være 5.000 tegn.');

$pdo = db();
$pdo->beginTransaction();
$committed = false;
try {
    $validated = [];
    $total = 0.0;
    $seen = [];
    $hasMeeting = false;
    $hasRegular = false;
    $lookup = $pdo->prepare('SELECT m.id,m.title,m.slug,m.price,m.xl_price,m.is_available,c.is_active category_active FROM menu_items m JOIN menu_categories c ON c.id=m.category_id WHERE m.id=?');
    foreach ($cart as $item) {
        if (!is_array($item) || (int)($item['id'] ?? 0) <= 0) throw new InvalidArgumentException('Bestillingen indeholder en ugyldig varelinje.');
        $lookup->execute([(int)$item['id']]);
        $row = $lookup->fetch();
        if (!$row) throw new InvalidArgumentException('En vare findes ikke længere. Opdater siden og vælg igen.');

        $qty = (int)($item['qty'] ?? 0);
        if ($qty < 1 || $qty > 99) throw new InvalidArgumentException('Antallet skal være mellem 1 og 99 pr. vare.');
        $size = strtolower(trim((string)($item['size'] ?? 'normal')));
        if (!in_array($size, ['normal', 'xl'], true)) throw new InvalidArgumentException('Ugyldig størrelse i bestillingen.');
        $lineKey = $row['id'] . ':' . $size;
        if (isset($seen[$lineKey])) throw new InvalidArgumentException('Samme vare står flere gange i bestillingen.');
        $seen[$lineKey] = true;

        $isMeeting = front_is_meeting_item($row);
        $hasMeeting = $hasMeeting || $isMeeting;
        $hasRegular = $hasRegular || !$isMeeting;
        if ((int)($row['is_available'] ?? 0) !== 1 || (int)($row['category_active'] ?? 0) !== 1) throw new InvalidArgumentException('En vare kan ikke bestilles lige nu. Opdater siden og vælg igen.');
        if ($isMeeting && ((string)setting('meeting_enabled', '1') === '0' || (string)setting('meeting_order_enabled', '1') === '0')) {
            throw new InvalidArgumentException('Mødeforplejning kan ikke bestilles lige nu. Opdater siden eller kontakt Café LIF.');
        }

        $normalPrice = (float)($isMeeting ? setting('meeting_order_price', $row['price'] ?? 0) : ($row['price'] ?? 0));
        $xlPrice = $isMeeting ? 0.0 : (float)($row['xl_price'] ?? 0);
        if ($size === 'xl' && $xlPrice <= 0) {
            throw new InvalidArgumentException('XL-størrelsen er ikke længere tilgængelig for ' . (string)$row['title'] . '. Opdater siden og vælg igen.');
        }

        $hasSizes = $xlPrice > 0;
        $price = $size === 'xl' ? $xlPrice : $normalPrice;
        $sizeLabel = $hasSizes ? ($size === 'xl' ? 'XL' : 'Alm.') : '';
        $itemName = (string)$row['title'] . ($isMeeting ? ' – pr. person' : ($sizeLabel !== '' ? ' – ' . $sizeLabel : ''));

        $validated[] = [
            'row' => $row,
            'qty' => $qty,
            'price' => $price,
            'size' => $size,
            'item_name' => $itemName,
        ];
        $total += $price * $qty;
    }
    if (!$validated) throw new InvalidArgumentException('Ingen gyldige valg blev fundet. Opdater siden og prøv igen.');
    $orderType = $hasMeeting && $hasRegular ? 'Blandet' : ($hasMeeting ? 'Mødeforplejning' : 'Takeaway');

    // Kunden bruger mobilnummeret til statusopslag, men hver bestilling får også sin egen unikke reference.
    // Det gør, at samme mobilnummer kan have flere samtidige eller fremtidige bestillinger uden forvirring.
    $stmt = $pdo->prepare('INSERT INTO orders(order_number,customer_name,phone,email,desired_date,desired_time,message,total_estimate,confirmation_email_requested) VALUES(?,?,?,?,?,?,?,?,?)');
    $saved = false;
    $reference = '';
    for ($try = 0; $try < 8 && !$saved; $try++) {
        $candidate = cafelif_order_reference();
        try {
            $stmt->execute([
                $candidate, $name, $phone, $email ?: null,
                $desiredDate ?: null,
                $desiredTime ?: null,
                $message ?: null,
                $total,
                $confirmationRequested ? 1 : 0
            ]);
            $reference = $candidate;
            $saved = true;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
        }
    }
    if (!$saved) throw new RuntimeException('Bestillingen kunne ikke få en reference.');

    $orderId = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO order_items(order_id,menu_item_id,item_name,quantity,unit_price) VALUES(?,?,?,?,?)');
    foreach ($validated as $item) {
        $insert->execute([$orderId, $item['row']['id'], $item['item_name'], $item['qty'], $item['price']]);
    }
    $pdo->commit();
    $committed = true;
    log_event('order_submit');

    $basePath = strtolower((string)(parse_url((string)($config['app']['base_url'] ?? ''), PHP_URL_PATH) ?: ''));
    $isTest = str_contains($basePath, '/test') || basename(dirname(__DIR__)) === 'dev';
    $environment = $isTest ? (basename(dirname(__DIR__)) === 'dev' ? 'dev' : 'test') : 'prod';
    $mailConfig = [];
    try { $mailConfig = cafelif_mail_config(); } catch (Throwable) { /* Ordren er allerede gemt. */ }
    // I Test går mail kun til en eksplicit testmodtager eller caféens egen SMTP-afsender.
    $testRecipient = trim((string)($mailConfig['test_recipient'] ?? ''));
    if ($testRecipient === '') $testRecipient = trim((string)($mailConfig['from_email'] ?? ''));
    $adminRecipient = trim((string)($mailConfig['admin_email'] ?? ''));
    $recipient = $isTest ? $testRecipient : $email;
    $subjectPrefix = $isTest ? '[TEST] ' : '';
    $mailLines = [
        'Café LIF' . ($isTest ? ' — TEST, ingen rigtig kundemail' : ''),
        'Ordrenummer: ' . $reference,
        'Navn: ' . $name,
        'Telefon: ' . $phone,
        'Email: ' . ($email ?: 'Ikke angivet'),
        'Original kunde-email: ' . ($email ?: 'Ikke angivet'),
        'Ordretype: ' . $orderType,
        'Ønsket dato: ' . ($desiredDate ?: 'Ikke angivet'),
        'Ønsket tidspunkt: ' . ($desiredTime ?: 'Ikke angivet'),
        'Varer:',
    ];
    foreach ($validated as $item) {
        $mailLines[] = $item['qty'] . ' × ' . $item['item_name'] . ' — ' . number_format((float)$item['price'], 2, ',', '.') . ' kr. pr. stk. — ' . number_format($item['qty'] * (float)$item['price'], 2, ',', '.') . ' kr.';
    }
    $mailLines[] = 'Totalestimat: ' . number_format($total, 2, ',', '.') . ' kr.';
    if ($message !== '') $mailLines[] = 'Besked: ' . $message;
    $mailLines[] = 'Tak for din bestilling. Café LIF kontakter dig ved spørgsmål.';
    $mailText = implode("\n", $mailLines);
    $mailHtml = '<div style="font-family:Arial,sans-serif;line-height:1.6;white-space:pre-line">' . htmlspecialchars($mailText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    $customerMailSent = false;
    $mailLog = static function (string $kind, string $actualRecipient, string $status, string $error = '') use ($reference, $environment, $confirmationRequested, $email): void {
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $record = [
            'timestamp' => date(DATE_ATOM), 'order_number' => $reference, 'environment' => $environment,
            'kind' => $kind, 'confirmation_email_requested' => $confirmationRequested,
            'original_customer_email' => $email, 'actual_recipient' => $actualRecipient,
            'status' => $status, 'error' => $error,
        ];
        if (@file_put_contents($dir . '/order-mail.log', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX) === false) error_log('Café LIF mailstatus kunne ikke logges for ' . $reference);
    };
    $sendOrderMail = static function (string $kind, string $to, string $toName, string $subject, string $columnSent, string $columnError) use ($pdo, $orderId, $mailHtml, $mailText, $mailLog): bool {
        $error = '';
        try {
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingen gyldig modtager er konfigureret.');
            send_cafelif_mail($to, $toName, $subject, $mailHtml, $mailText);
        } catch (Throwable $e) {
            $error = 'Mail kunne ikke sendes';
            error_log('Café LIF ' . $kind . ' mailfejl for ' . $orderId . ': ' . $error);
        }
        try {
            $pdo->prepare("UPDATE orders SET $columnSent=IF(?='',NOW(),NULL), $columnError=? WHERE id=?")->execute([$error, $error ?: null, $orderId]);
        } catch (Throwable) { error_log('Café LIF mailstatus kunne ikke gemmes for ' . $orderId); }
        $mailLog($kind, $to, $error === '' ? 'sent' : 'failed', $error);
        return $error === '';
    };
    if ($confirmationRequested) {
        $customerMailSent = $sendOrderMail('customer', $recipient, $name, $subjectPrefix . 'Ordrebekræftelse fra Café LIF – ' . $reference, 'customer_mail_sent_at', 'customer_mail_error');
    }
    if ($adminRecipient !== '') {
        $sendOrderMail('admin', $isTest ? $testRecipient : $adminRecipient, 'Café LIF', $subjectPrefix . 'Ny bestilling hos Café LIF – ' . $reference, 'admin_mail_sent_at', 'admin_mail_error');
    }

    cafelif_order_respond([
        'ok' => true,
        'reference' => $reference,
        'phone' => $phoneKey,
        'confirmation_email_requested' => $confirmationRequested,
        'customer_mail_sent' => $customerMailSent,
        'test_mode' => $isTest,
        'message' => 'Bestillingen er sendt til Café LIF.',
    ]);
} catch (Throwable $e) {
    if ($committed) {
        error_log('Café LIF mailbehandling fejlede efter gemt ordre ' . $reference);
        cafelif_order_respond(['ok' => true, 'reference' => $reference, 'phone' => $phoneKey,
            'confirmation_email_requested' => $confirmationRequested, 'customer_mail_sent' => false,
            'test_mode' => true,
            'message' => 'Bestillingen er gemt, men mailstatus kunne ikke bekræftes.']);
    }
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof InvalidArgumentException) {
        cafelif_order_fail($e->getMessage(), 400);
    }
    error_log('Café LIF bestilling fejl: ' . $e->getMessage());
    cafelif_order_fail('Bestillingen kunne ikke gemmes. Prøv igen eller ring til Café LIF.', 500);
}
