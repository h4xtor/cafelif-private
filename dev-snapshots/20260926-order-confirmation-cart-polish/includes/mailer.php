<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';

function cafelif_mail_config(): array
{
    $path = dirname(__DIR__) . '/config.mail.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Mailopsætningen mangler. Kør admin/security-setup.php.');
    }

    $mail = require $path;
    if (!is_array($mail)) {
        throw new RuntimeException('Mailopsætningen er ugyldig.');
    }

    $required = ['host', 'port', 'username', 'password', 'from_email', 'from_name'];
    foreach ($required as $key) {
        if (!isset($mail[$key]) || trim((string)$mail[$key]) === '') {
            throw new RuntimeException("Mailopsætningen mangler feltet '$key'.");
        }
    }

    $mail['host'] = strtolower(trim((string)$mail['host']));
    $mail['port'] = (int)$mail['port'];
    $mail['username'] = strtolower(trim((string)$mail['username']));
    $mail['from_email'] = strtolower(trim((string)$mail['from_email']));
    $mail['encryption'] = strtolower((string)($mail['encryption'] ?? 'tls'));
    $mail['timeout'] = max(5, min(30, (int)($mail['timeout'] ?? 15)));

    if ($mail['host'] !== 'websmtp.simply.com' || $mail['port'] !== 587 || $mail['encryption'] !== 'tls') {
        throw new RuntimeException('Mailopsætningen skal bruge websmtp.simply.com på port 587 med STARTTLS.');
    }
    if (!filter_var($mail['username'], FILTER_VALIDATE_EMAIL) ||
        !filter_var($mail['from_email'], FILTER_VALIDATE_EMAIL) ||
        !str_ends_with($mail['username'], '@cafelif.dk') ||
        !str_ends_with($mail['from_email'], '@cafelif.dk')) {
        throw new RuntimeException('Mailopsætningen indeholder en ugyldig afsender eller SMTP-loginadresse.');
    }

    return $mail;
}

function mail_value_has_header_break(string $value): bool
{
    return str_contains($value, "\r") || str_contains($value, "\n");
}

function send_cafelif_mail(string $toEmail, string $toName, string $subject, string $html, string $text): void
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) ||
        mail_value_has_header_break($toName) ||
        mail_value_has_header_break($subject)) {
        throw new InvalidArgumentException('Mailen indeholder et ugyldigt modtager- eller headerfelt.');
    }

    $config = cafelif_mail_config();
    $mailer = new PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $mailer->SMTPAuth = true;
        $mailer->AuthType = 'LOGIN';
        $mailer->Username = $config['username'];
        $mailer->Password = (string)$config['password'];
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAutoTLS = true;
        $mailer->Timeout = $config['timeout'];
        $mailer->Timelimit = $config['timeout'];
        $mailer->SMTPDebug = 0;
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Encoding = PHPMailer::ENCODING_BASE64;
        $mailer->setFrom($config['from_email'], (string)$config['from_name'], false);
        $mailer->addAddress($toEmail, $toName);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        $mailer->send();
    } catch (PHPMailerException $exception) {
        throw new RuntimeException('SMTP-serveren afviste eller kunne ikke levere mailen.', 0, $exception);
    }
}
