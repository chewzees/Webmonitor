<?php
declare(strict_types=1);

/**
 * Cron entry — check due active websites.
 *   php cli/monitor.php
 */

require dirname(__DIR__) . '/includes/app.php';

try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$sites = $pdo->query('SELECT * FROM websites WHERE is_active = 1')->fetchAll();
$checked = 0;

foreach ($sites as $site) {
    $interval = max(30, (int) $site['interval_seconds']);
    $due = true;
    if (!empty($site['last_checked_at'])) {
        try {
            $last = new DateTimeImmutable((string) $site['last_checked_at'], new DateTimeZone('UTC'));
            $due = $last->modify("+{$interval} seconds") <= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable) {
            $due = true;
        }
    }
    if (!$due) {
        continue;
    }
    $result = Monitor::runCheck($site);
    echo $site['name'] . ' => ' . $result['status'] . PHP_EOL;
    $checked++;
}

echo "Checked {$checked} site(s).\n";
