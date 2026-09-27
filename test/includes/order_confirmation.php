<?php
declare(strict_types=1);
require_once __DIR__ . '/mailer.php';

/** Rebuild receipts from saved items so resends preserve the original prices. */
function cafelif_send_order_confirmation(PDO $pdo, int $orderId, array $config, bool $manual = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || !empty($order['deleted_at'])) throw new RuntimeException('Ordren findes ikke eller ligger i papirkurven.');
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
    $stmt->execute([$orderId]);
    $validated = array_map(static fn(array $item): array => [
        'qty' => (int)$item['quantity'], 'price' => (float)$item['unit_price'], 'item_name' => $item['item_name'],
    ], $stmt->fetchAll());
    $name = (string)$order['customer_name'];
    $phone = (string)$order['phone'];
    $email = (string)($order['email'] ?? '');
    if ($manual && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ordren mangler en gyldig e-mailadresse.');
    $reference = (string)$order['order_number'];
    $desiredDate = (string)($order['desired_date'] ?? '');
    $desiredTime = (string)($order['desired_time'] ?? '');
    $message = (string)($order['message'] ?? '');
    $total = (float)$order['total_estimate'];
    $confirmationRequested = !empty($order['confirmation_email_requested']);
    $orderType = 'Bestilling';
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
        'Ordretype: ' . $orderType,
        'Ønsket dato: ' . ($desiredDate ?: 'Ikke angivet'),
        'Ønsket tidspunkt: ' . ($desiredTime ?: 'Ikke angivet'),
        'Varer:',
    ];
    $htmlRows = '';
    foreach ($validated as $item) {
        $lineTotal = (float)$item['qty'] * (float)$item['price'];
        $mailLines[] = $item['qty'] . ' × ' . $item['item_name'] . ' — ' . number_format((float)$item['price'], 2, ',', '.') . ' kr. pr. stk. — ' . number_format($lineTotal, 2, ',', '.') . ' kr.';
        $htmlRows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eadfd5">' . (int)$item['qty'] . ' × ' . h($item['item_name']) . '<br><span style="color:#7a6b62;font-size:13px">' . h(money($item['price'])) . ' pr. stk.</span></td><td style="padding:10px 0;border-bottom:1px solid #eadfd5;text-align:right;white-space:nowrap">' . h(money($lineTotal)) . '</td></tr>';
    }
    $mailLines[] = 'Totalestimat: ' . number_format($total, 2, ',', '.') . ' kr.';
    if ($message !== '') $mailLines[] = 'Besked: ' . $message;
    $mailLines[] = 'Tak for din bestilling. Café LIF kontakter dig ved spørgsmål.';
    $mailText = implode("\n", $mailLines);
    $safeName = h($name);
    $safeReference = h($reference);
    $safePhone = h($phone);
    $safeEmail = h($email ?: 'Ikke angivet');
    $safeDate = h($desiredDate ?: 'Ikke angivet');
    $safeTime = h($desiredTime ?: 'Ikke angivet');
    $safeMessage = h($message);
    $mailHtml = '<div style="background:#f7f1eb;padding:28px 12px;font-family:Arial,sans-serif;color:#2b211d">'
        . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #eadfd5;border-radius:18px;overflow:hidden">'
        . '<div style="background:#2b211d;color:#fff;padding:26px 28px"><div style="font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:#e8cfc2">Café LIF</div><h1 style="margin:8px 0 0;font-size:26px;font-weight:500">Ordrebekræftelse</h1></div>'
        . '<div style="padding:28px"><p style="margin:0 0 18px;font-size:16px">Hej ' . $safeName . ',</p><p style="margin:0 0 22px;line-height:1.6">Tak for din bestilling hos Café LIF. Her er en oversigt over det, du har sendt.</p>'
        . '<div style="background:#f7f1eb;border-radius:12px;padding:16px 18px;margin-bottom:22px"><strong>Ordrenummer</strong><br><span style="font-size:20px;color:#bf1e2e">' . $safeReference . '</span></div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse"><tbody>' . $htmlRows . '<tr><td style="padding:18px 0 0;font-size:17px"><strong>Estimeret total</strong></td><td style="padding:18px 0 0;text-align:right;font-size:20px;color:#bf1e2e;white-space:nowrap"><strong>' . h(money($total)) . '</strong></td></tr></tbody></table>'
        . '<div style="border-top:1px solid #eadfd5;margin-top:22px;padding-top:18px;line-height:1.7"><strong>Afhentning</strong><br>' . $safeDate . ' kl. ' . $safeTime . '<br><br><strong>Kontaktoplysninger</strong><br>' . $safePhone . '<br>' . $safeEmail
        . ($message !== '' ? '<br><br><strong>Besked</strong><br>' . $safeMessage : '') . '</div>'
        . '<p style="margin:24px 0 0;line-height:1.6">Vi kontakter dig, hvis vi har spørgsmål til bestillingen. Denne mail er en kvittering for modtagelsen; afhentningstidspunktet aftales med Café LIF.</p></div>'
        . '<div style="padding:18px 28px;background:#f7f1eb;color:#7a6b62;font-size:13px;line-height:1.6">Café LIF<br>Har du spørgsmål, så ring til os. Denne mailadresse modtager ikke svar.</div></div></div>';
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
            $pdo->prepare("UPDATE orders SET $columnSent=IF(?='',NOW(),$columnSent), $columnError=? WHERE id=?")->execute([$error, $error ?: null, $orderId]);
        } catch (Throwable) { error_log('Café LIF mailstatus kunne ikke gemmes for ' . $orderId); }
        $mailLog($kind, $to, $error === '' ? 'sent' : 'failed', $error);
        return $error === '';
    };
    if ($confirmationRequested || $manual) {
        $customerMailSent = $sendOrderMail('customer', $recipient, $name, $subjectPrefix . 'Ordrebekræftelse fra Café LIF – ' . $reference, 'customer_mail_sent_at', 'customer_mail_error');
    }
    if (!$manual && $adminRecipient !== '') {
        $sendOrderMail('admin', $isTest ? $testRecipient : $adminRecipient, 'Café LIF', $subjectPrefix . 'Ny bestilling hos Café LIF – ' . $reference, 'admin_mail_sent_at', 'admin_mail_error');
    }

    return ['customer_mail_sent' => $customerMailSent, 'test_mode' => $isTest];
}
