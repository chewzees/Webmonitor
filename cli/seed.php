<?php
declare(strict_types=1);

/**
 * Seed admin + demo user.
 *   php cli/seed.php
 */

require dirname(__DIR__) . '/includes/app.php';

try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function upsertUser(PDO $pdo, string $email, string $password, string $name, string $role): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower($email)]);
    $existing = $stmt->fetch();
    if ($existing) {
        $pdo->prepare(
            'UPDATE users SET password_hash = :h, name = :n, role = :r, updated_at = UTC_TIMESTAMP(3) WHERE id = :id'
        )->execute(['h' => $hash, 'n' => $name, 'r' => $role, 'id' => $existing['id']]);
        echo "Updated {$role}: {$email}\n";
        return;
    }
    $pdo->prepare(
        'INSERT INTO users (id, email, password_hash, name, role, created_at, updated_at)
         VALUES (:id, :email, :h, :n, :r, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))'
    )->execute([
        'id' => cuid(),
        'email' => strtolower($email),
        'h' => $hash,
        'n' => $name,
        'r' => $role,
    ]);
    echo "Created {$role}: {$email}\n";
}

upsertUser(
    $pdo,
    Env::get('ADMIN_EMAIL', 'admin@webmonitor.local'),
    Env::get('ADMIN_PASSWORD', 'ChangeMe123!'),
    Env::get('ADMIN_NAME', 'Admin'),
    'ADMIN'
);

upsertUser(
    $pdo,
    Env::get('USER_EMAIL', 'user@webmonitor.local'),
    Env::get('USER_PASSWORD', 'User123!'),
    Env::get('USER_NAME', 'Demo User'),
    'USER'
);

$tg = $pdo->query('SELECT id FROM telegram_settings LIMIT 1')->fetch();
if (!$tg) {
    try {
        $pdo->prepare(
            'INSERT INTO telegram_settings (id, bot_token, chat_id, enabled, notify_on_down, notify_on_up, created_at, updated_at)
             VALUES (:id, \'\', \'\', 0, 1, 1, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))'
        )->execute(['id' => cuid()]);
        echo "Created telegram_settings row\n";
    } catch (Throwable $e) {
        echo "telegram_settings skipped: {$e->getMessage()}\n";
    }
}

$check = $pdo->prepare('SELECT id FROM websites WHERE slug = :slug LIMIT 1');
$check->execute(['slug' => 'example']);
if (!$check->fetch()) {
    $pdo->prepare(
        'INSERT INTO websites (
            id, name, url, slug, description, method, interval_seconds, timeout_ms,
            expected_status, is_active, is_public, current_status, created_at, updated_at
         ) VALUES (
            :id, \'Example\', \'https://example.com\', \'example\', \'Sample monitor\',
            \'GET\', 60, 10000, 200, 1, 1, \'UNKNOWN\', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
         )'
    )->execute(['id' => cuid()]);
    echo "Created sample website: example.com\n";
}

echo "Seed complete.\n";
