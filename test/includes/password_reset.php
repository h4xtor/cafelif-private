<?php
declare(strict_types=1);

function password_reset_request_is_rate_limited(?int $adminId): bool
{
    $ipHash = privacy_hash($_SERVER['REMOTE_ADDR'] ?? '');
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM admin_security_events
             WHERE event_type = \'password_reset_requested\'
               AND ip_hash = ?
               AND created_at >= (NOW() - INTERVAL 60 MINUTE)'
        );
        $stmt->execute([$ipHash]);
        if ((int)$stmt->fetchColumn() >= 5) {
            return true;
        }

        if ($adminId !== null) {
            $stmt = db()->prepare(
                'SELECT COUNT(*)
                 FROM admin_password_resets
                 WHERE admin_id = ?
                   AND created_at >= (NOW() - INTERVAL 60 MINUTE)'
            );
            $stmt->execute([$adminId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                return true;
            }
        }
    } catch (Throwable) {
        return !rate_limit('password_reset_fallback', 5, 3600);
    }

    return false;
}

function request_admin_password_reset(string $email): void
{
    $email = mb_strtolower(trim($email));
    $admin = null;
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare(
            'SELECT id,name,email
             FROM admins
             WHERE LOWER(email) = ? AND is_active = 1 AND role <> \'superadmin\'
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch() ?: null;
    }

    $adminId = $admin ? (int)$admin['id'] : null;
    if (password_reset_request_is_rate_limited($adminId)) {
        record_admin_security_event('password_reset_rate_limited', $adminId);
        return;
    }

    record_admin_security_event('password_reset_requested', $adminId);

    if (!$admin) {
        record_admin_security_event('password_reset_unknown_email');
        usleep(random_int(250000, 550000));
        return;
    }

    $selector = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    db()->beginTransaction();
    try {
        $expireOld = db()->prepare(
            'UPDATE admin_password_resets
             SET used_at = COALESCE(used_at, NOW())
             WHERE admin_id = ? AND used_at IS NULL'
        );
        $expireOld->execute([$adminId]);

        $insert = db()->prepare(
            "INSERT INTO admin_password_resets
                (admin_id,channel,selector,token_hash,request_ip_hash,attempts,expires_at,used_at,created_at)
             VALUES (?,'email',?,?,?,0,(NOW() + INTERVAL 30 MINUTE),NULL,NOW())"
        );
        $insert->execute([
            $adminId,
            $selector,
            $tokenHash,
            privacy_hash($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }

    $resetUrl = base_url('/admin/reset-password.php?selector=' . rawurlencode($selector) . '&token=' . rawurlencode($token));
    $safeName = h((string)$admin['name']);
    $safeUrl = h($resetUrl);
    $subject = 'Nulstil din adgangskode til Café LIF';
    $html = '<!doctype html><html lang="da"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">'
        . '<h1 style="font-size:24px">Ny adgangskode til Café LIF</h1>'
        . '<p>Hej ' . $safeName . '</p>'
        . '<p>Der er bedt om at vælge en ny adgangskode til din administratorkonto.</p>'
        . '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 18px;background:#164e63;color:#fff;text-decoration:none;border-radius:8px">Vælg ny adgangskode</a></p>'
        . '<p>Linket virker én gang og udløber efter 30 minutter.</p>'
        . '<p>Hvis du ikke har bedt om dette, kan du ignorere mailen. Din nuværende adgangskode ændres ikke.</p>'
        . '<p>Café LIF</p></body></html>';
    $text = "Hej {$admin['name']}\n\n"
        . "Der er bedt om at vælge en ny adgangskode til din administratorkonto.\n\n"
        . "Åbn dette link: $resetUrl\n\n"
        . "Linket virker én gang og udløber efter 30 minutter.\n"
        . "Hvis du ikke har bedt om dette, kan du ignorere mailen.\n\nCafé LIF";

    try {
        send_cafelif_mail((string)$admin['email'], (string)$admin['name'], $subject, $html, $text);
        record_admin_security_event('password_reset_email_sent', $adminId);
    } catch (Throwable $exception) {
        $invalidate = db()->prepare(
            'UPDATE admin_password_resets SET used_at = NOW() WHERE selector = ? AND used_at IS NULL'
        );
        $invalidate->execute([$selector]);
        record_admin_security_event('password_reset_email_failed', $adminId);
        error_log('Café LIF password reset mail failed: ' . $exception->getMessage());
    }
}

function password_reset_record(string $selector): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $selector)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT r.id,r.admin_id,r.token_hash,r.attempts,r.expires_at,r.used_at,a.name,a.email
         FROM admin_password_resets r
         INNER JOIN admins a ON a.id = r.admin_id
         WHERE r.selector = ? AND a.is_active = 1 AND a.role <> \'superadmin\'
         LIMIT 1'
    );
    $stmt->execute([$selector]);
    return $stmt->fetch() ?: null;
}

function password_reset_link_is_valid(string $selector, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $record = password_reset_record($selector);
    if (!$record || $record['used_at'] !== null || (int)$record['attempts'] >= 5) {
        return false;
    }

    if (strtotime((string)$record['expires_at']) < time()) {
        return false;
    }

    return hash_equals((string)$record['token_hash'], hash('sha256', $token));
}

function consume_admin_password_reset(string $selector, string $token, string $password): bool
{
    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'SELECT r.id,r.admin_id,r.token_hash,r.attempts,r.expires_at,r.used_at
             FROM admin_password_resets r
             INNER JOIN admins a ON a.id = r.admin_id
              WHERE r.selector = ? AND a.is_active = 1 AND a.role <> \'superadmin\'
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$selector]);
        $record = $stmt->fetch();

        $valid = $record &&
            $record['used_at'] === null &&
            (int)$record['attempts'] < 5 &&
            strtotime((string)$record['expires_at']) >= time() &&
            preg_match('/^[a-f0-9]{64}$/', $token) === 1 &&
            hash_equals((string)$record['token_hash'], hash('sha256', $token));

        if (!$valid) {
            if ($record && $record['used_at'] === null) {
                $attempt = db()->prepare('UPDATE admin_password_resets SET attempts = attempts + 1 WHERE id = ?');
                $attempt->execute([(int)$record['id']]);
            }
            db()->commit();
            record_admin_security_event('password_reset_invalid_token', $record ? (int)$record['admin_id'] : null);
            return false;
        }

        $adminId = (int)$record['admin_id'];
        $updateAdmin = db()->prepare(
            'UPDATE admins
             SET password_hash = ?,
                 must_change_password = 0,
                 password_changed_at = NOW(),
                 session_version = session_version + 1
             WHERE id = ?'
        );
        $updateAdmin->execute([password_hash($password, PASSWORD_DEFAULT), $adminId]);

        $consume = db()->prepare(
            'UPDATE admin_password_resets
             SET used_at = NOW()
             WHERE admin_id = ? AND used_at IS NULL'
        );
        $consume->execute([$adminId]);
        db()->commit();
        record_admin_security_event('password_reset_completed', $adminId);
        return true;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }
}
