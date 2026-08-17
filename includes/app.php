<?php
declare(strict_types=1);

/**
 * App bootstrap — HTML/CSS/JS + PHP + MySQL only.
 */

require_once __DIR__ . '/../lib/Env.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/Monitor.php';

$envFile = dirname(__DIR__) . '/api/.env';
if (!is_file($envFile)) {
    $envFile = dirname(__DIR__) . '/.env';
}
Env::load($envFile);

$https =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

$secure = Env::bool('COOKIE_SECURE', false) || $https;

session_name('webmonitor_sid');
session_set_cookie_params([
    'lifetime' => 7 * 24 * 60 * 60,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

csrf_token();
