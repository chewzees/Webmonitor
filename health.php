<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');

$db = DB::ok() ? 'ok' : 'error';
$payload = [
    'status' => $db === 'ok' ? 'ok' : 'degraded',
    'db' => $db,
    'stack' => 'php-mysql',
    'timestamp' => gmdate('c'),
];
if ($db !== 'ok') {
    $payload['hint'] = 'Edit api/.env DB_* values and import database/schema.sql';
}
http_response_code($db === 'ok' ? 200 : 503);
echo json_encode($payload, JSON_UNESCAPED_SLASHES);
