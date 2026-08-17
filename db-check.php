<?php
/**
 * DB connectivity check — DELETE after setup works.
 * /Webmonitor/db-check.php?key=setup
 */
declare(strict_types=1);

if ((string) ($_GET['key'] ?? '') !== 'setup') {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/lib/Env.php';

try {
    $envFile = __DIR__ . '/api/.env';
    if (!is_file($envFile)) {
        $envFile = __DIR__ . '/.env';
    }
    Env::load($envFile);
} catch (Throwable $e) {
    echo "ENV LOAD FAILED\n" . $e->getMessage() . "\n";
    exit;
}

$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$name = Env::get('DB_NAME', 'webmonitor');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');

echo "WebMonitor DB check (PHP + MySQL)\n";
echo "--------------------------------\n";
echo "DB_HOST={$host}\nDB_PORT={$port}\nDB_NAME={$name}\nDB_USER={$user}\n";
echo 'DB_PASS=' . ($pass === '' ? '(empty)' : '(set)') . "\n\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "CONNECTION: OK\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'TABLES: ' . (count($tables) ? implode(', ', $tables) : '(none)') . "\n";
    if (!in_array('websites', $tables, true) || !in_array('users', $tables, true)) {
        echo "\nImport database/schema.sql then run: php cli/seed.php\n";
    } else {
        echo 'users: ' . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";
        echo 'websites: ' . $pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn() . "\n";
        echo "\nAll good. DELETE db-check.php now.\n";
    }
} catch (Throwable $e) {
    echo "CONNECTION: FAILED\n" . $e->getMessage() . "\n";
    echo "\nTip: on shared hosting use DB_HOST=localhost and your cPanel DB user/password.\n";
}
