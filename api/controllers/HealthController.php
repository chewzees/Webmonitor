<?php
declare(strict_types=1);

final class HealthController
{
    private static ?float $startedAt = null;

    public static function check(Request $request): void
    {
        if (self::$startedAt === null) {
            self::$startedAt = microtime(true);
        }

        $db = 'error';
        try {
            Database::connection()->query('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'error';
        }

        $ok = $db === 'ok';
        $payload = [
            'status' => $ok ? 'ok' : 'degraded',
            'uptime' => (int) floor(microtime(true) - self::$startedAt),
            'db' => $db,
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
        if (!$ok) {
            $payload['hint'] = 'Check api/.env DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS and that the MySQL database exists.';
        }
        Response::json($payload, $ok ? 200 : 503);
    }
}
