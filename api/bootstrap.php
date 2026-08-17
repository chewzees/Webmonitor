<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Request.php';
require_once __DIR__ . '/lib/Cuid.php';
require_once __DIR__ . '/lib/Validator.php';

require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/Csrf.php';
require_once __DIR__ . '/middleware/RateLimit.php';

require_once __DIR__ . '/services/AuditService.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/TelegramService.php';
require_once __DIR__ . '/services/UptimeService.php';
require_once __DIR__ . '/services/MonitorService.php';
require_once __DIR__ . '/services/WebsiteService.php';
require_once __DIR__ . '/services/WebsitePreviewService.php';
require_once __DIR__ . '/services/LogService.php';
require_once __DIR__ . '/services/DashboardService.php';
require_once __DIR__ . '/services/PublicService.php';
require_once __DIR__ . '/services/EventBus.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/WebsiteController.php';
require_once __DIR__ . '/controllers/LogController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/PublicController.php';
require_once __DIR__ . '/controllers/TelegramController.php';
require_once __DIR__ . '/controllers/AuditController.php';
require_once __DIR__ . '/controllers/HealthController.php';
require_once __DIR__ . '/controllers/EventsController.php';

date_default_timezone_set('UTC');

// Session setup — cookie name matches Node (webmonitor.sid) via session.name when possible
$sessionName = 'webmonitor.sid';
if (!preg_match('/^[a-zA-Z0-9,-]+$/', $sessionName)) {
    $sessionName = 'webmonitor_sid';
}
session_name($sessionName);

$secure = !empty($config['cookie_secure']);
// Auto-enable Secure cookies behind HTTPS (shared hosting / Cloudflare / proxies)
if (!$secure) {
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $secure = $https;
}

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

return $config;
