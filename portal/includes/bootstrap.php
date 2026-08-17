<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/lib/Env.php';
require_once dirname(__DIR__, 2) . '/api/lib/Database.php';

Env::load(dirname(__DIR__, 2) . '/api/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/PortalRepository.php';

function portal_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portal_is_admin(): bool
{
    return !empty($_SESSION['portal_admin']);
}

function portal_require_admin(): void
{
    if (!portal_is_admin()) {
        header('Location: add-project.php');
        exit;
    }
}

function portal_flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['portal_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['portal_flash'] ?? null;
    unset($_SESSION['portal_flash']);
    return is_array($flash) ? $flash : null;
}
