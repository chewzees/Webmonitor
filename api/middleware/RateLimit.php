<?php
declare(strict_types=1);

final class RateLimit
{
    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/rate_limit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @return array{ok: bool, remaining: int, retryAfter?: int}
     */
    public static function attempt(string $key, int $max, int $windowSeconds): array
    {
        $file = self::dir() . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $data = ['starts' => $now, 'count' => 0];

        if (is_file($file)) {
            $raw = file_get_contents($file);
            $parsed = $raw ? json_decode($raw, true) : null;
            if (is_array($parsed) && isset($parsed['starts'], $parsed['count'])) {
                $data = $parsed;
            }
        }

        if ($now - (int) $data['starts'] >= $windowSeconds) {
            $data = ['starts' => $now, 'count' => 0];
        }

        if ((int) $data['count'] >= $max) {
            $retry = $windowSeconds - ($now - (int) $data['starts']);
            return ['ok' => false, 'remaining' => 0, 'retryAfter' => max(1, $retry)];
        }

        $data['count'] = (int) $data['count'] + 1;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return ['ok' => true, 'remaining' => max(0, $max - (int) $data['count'])];
    }

    public static function assertLogin(Request $request): void
    {
        $ip = $request->ip() ?? 'unknown';
        $result = self::attempt('login:' . $ip, 10, 15 * 60);
        if (!$result['ok']) {
            if (isset($result['retryAfter'])) {
                header('Retry-After: ' . $result['retryAfter']);
            }
            throw new AppException(
                'Too many login attempts. Please try again later.',
                429,
                'RATE_LIMITED',
            );
        }
    }
}
