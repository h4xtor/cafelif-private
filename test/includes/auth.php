<?php
declare(strict_types=1);

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id']) || !isset($_SESSION['admin_session_version'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id,name,email,role,must_change_password,last_login_at,session_version
         FROM admins
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || (int)$admin['session_version'] !== (int)$_SESSION['admin_session_version']) {
        admin_clear_session();
        return null;
    }

    return $admin;
}

function require_admin(bool $allowPasswordChange = false): void
{
    $admin = current_admin();
    if (!$admin) {
        redirect('/admin/login.php');
    }

    if (!$allowPasswordChange && (int)$admin['must_change_password'] === 1) {
        redirect('/admin/password.php');
    }
}

function is_superadmin(?array $admin = null): bool
{
    $admin ??= current_admin();
    return $admin !== null && ($admin['role'] ?? '') === 'superadmin';
}

function admin_clear_session(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_session_version']);
}

function record_admin_security_event(string $eventType, ?int $adminId = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO admin_security_events
                (admin_id,event_type,ip_hash,user_agent_hash,metadata_json,created_at)
             VALUES (?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            $adminId,
            mb_substr($eventType, 0, 60),
            privacy_hash($_SERVER['REMOTE_ADDR'] ?? ''),
            privacy_hash($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable) {
        // Sikkerhedslogning må ikke gøre adminområdet utilgængeligt.
    }
}

function admin_login_is_rate_limited(): bool
{
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
             FROM admin_security_events
             WHERE event_type = 'admin_login_failed'
               AND ip_hash = ?
               AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
        );
        $stmt->execute([privacy_hash($_SERVER['REMOTE_ADDR'] ?? '')]);
        return (int)$stmt->fetchColumn() >= 8;
    } catch (Throwable) {
        return !rate_limit('admin_login_fallback', 8, 900);
    }
}

function attempt_admin_login(string $login, string $password): bool
{
    if (admin_login_is_rate_limited()) {
        return false;
    }

    $login = mb_strtolower(trim($login));
    $stmt = db()->prepare(
        'SELECT *
         FROM admins
         WHERE (LOWER(email) = ? OR LOWER(name) = ?) AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$login, $login]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
        record_admin_security_event('admin_login_failed', $admin ? (int)$admin['id'] : null);
        return false;
    }

    if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int)$admin['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_session_version'] = (int)$admin['session_version'];

    $stmt = db()->prepare(
        'UPDATE admins
         SET previous_login_at = last_login_at,
             last_login_at = NOW(),
             last_login_ip_hash = ?
         WHERE id = ?'
    );
    $stmt->execute([privacy_hash($_SERVER['REMOTE_ADDR'] ?? ''), (int)$admin['id']]);
    record_admin_security_event('admin_login_succeeded', (int)$admin['id']);
    return true;
}

function admin_logout(): void
{
    $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    if ($adminId !== null) {
        record_admin_security_event('admin_logout', $adminId);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'] ?? '',
            'secure' => (bool)$parameters['secure'],
            'httponly' => (bool)$parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

