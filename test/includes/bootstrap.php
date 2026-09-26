<?php
declare(strict_types=1);

if (!defined('CAFELIF_BOOTSTRAPPED')) {
    define('CAFELIF_BOOTSTRAPPED', true);
}

$root = dirname(__DIR__);
$configFile = $root . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Manglende config.local.php. Kopiér config.local.php.example og udfyld databaseoplysningerne.');
}

$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Copenhagen');

if (($config['app']['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

$configuredBaseUrl = (string)($config['app']['base_url'] ?? '/');
if (strtolower((string)parse_url($configuredBaseUrl, PHP_URL_SCHEME)) !== 'https') {
    http_response_code(503);
    exit('Adminområdet kræver en HTTPS-baseadresse i serverkonfigurationen.');
}
$sessionBasePath = (string)(parse_url($configuredBaseUrl, PHP_URL_PATH) ?: '/');
$sessionBasePath = '/' . trim($sessionBasePath, '/') . '/';
$sessionBasePath = $sessionBasePath === '//' ? '/' : $sessionBasePath;
$sessionNamespace = substr(hash('sha256', $configuredBaseUrl), 0, 12);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('cafelif_admin_' . $sessionNamespace);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $sessionBasePath,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $root . '/includes/db.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/csrf.php';
require_once $root . '/includes/auth.php';
require_once $root . '/includes/mailer.php';
require_once $root . '/includes/password_reset.php';
require_once $root . '/includes/upload.php';
