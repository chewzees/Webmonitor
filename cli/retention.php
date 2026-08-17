<?php
declare(strict_types=1);

/**
 * Purge monitor logs older than LOG_RETENTION_DAYS (default 90).
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Webmonitor\cli\retention.php
 */

require dirname(__DIR__) . '/api/bootstrap.php';

try {
    $deleted = MonitorService::purgeOldLogs();
    echo '[' . gmdate('c') . "] Purged {$deleted} old monitor log(s)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Retention failed: ' . $e->getMessage() . "\n");
    exit(1);
}
