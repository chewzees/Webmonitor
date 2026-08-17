<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Env.php';

Env::load(__DIR__ . '/.env');

return [
    'db' => [
        'host' => Env::getString('DB_HOST', '127.0.0.1'),
        'port' => Env::getString('DB_PORT', '3306'),
        'name' => Env::getString('DB_NAME', 'webmonitor'),
        'user' => Env::getString('DB_USER', 'root'),
        'pass' => Env::getString('DB_PASS', ''),
    ],
    'session_secret' => Env::getString('SESSION_SECRET', 'change-me-long-secret'),
    'cors_origin' => Env::getList('CORS_ORIGIN', 'http://localhost:5173,http://localhost'),
    'cookie_secure' => Env::getBool('COOKIE_SECURE', false),
    'log_retention_days' => Env::getInt('LOG_RETENTION_DAYS', 90),
    'stale_multiplier' => (float) Env::getString('STALE_MULTIPLIER', '2'),
    'app_url' => Env::getString('APP_URL', 'http://localhost/Webmonitor'),
    'admin_email' => Env::getString('ADMIN_EMAIL', 'admin@webmonitor.local'),
    'admin_password' => Env::getString('ADMIN_PASSWORD', 'ChangeMe123!'),
    'admin_name' => Env::getString('ADMIN_NAME', 'Admin'),
];
