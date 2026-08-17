<?php
declare(strict_types=1);

/** HTTP check + uptime helpers (plain PHP). */
final class Monitor
{
    /** @return array{status:string,status_code:?int,response_ms:?int,error:?string} */
    public static function checkUrl(
        string $url,
        string $method = 'GET',
        int $timeoutMs = 10000,
        int $expectedStatus = 200,
        ?string $keyword = null
    ): array {
        $start = hrtime(true);
        $statusCode = null;
        $error = null;
        $body = '';

        if (!function_exists('curl_init')) {
            return [
                'status' => 'DOWN',
                'status_code' => null,
                'response_ms' => null,
                'error' => 'cURL extension is not enabled on this server.',
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $timeoutMs),
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_USERAGENT => 'WebMonitor/2.0',
            CURLOPT_CUSTOMREQUEST => strtoupper($method) === 'HEAD' ? 'HEAD' : 'GET',
            CURLOPT_NOBODY => strtoupper($method) === 'HEAD',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch) ?: 'Request failed';
        } else {
            $body = is_string($result) ? $result : '';
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        curl_close($ch);

        $ms = (int) round((hrtime(true) - $start) / 1e6);

        if ($error) {
            return ['status' => 'DOWN', 'status_code' => $statusCode, 'response_ms' => $ms, 'error' => $error];
        }

        if ($statusCode !== $expectedStatus) {
            return [
                'status' => 'DOWN',
                'status_code' => $statusCode,
                'response_ms' => $ms,
                'error' => "Expected HTTP {$expectedStatus}, got {$statusCode}",
            ];
        }

        if ($keyword !== null && $keyword !== '' && stripos($body, $keyword) === false) {
            return [
                'status' => 'DEGRADED',
                'status_code' => $statusCode,
                'response_ms' => $ms,
                'error' => 'Expected keyword not found in response',
            ];
        }

        return ['status' => 'UP', 'status_code' => $statusCode, 'response_ms' => $ms, 'error' => null];
    }

    public static function runCheck(array $website): array
    {
        $result = self::checkUrl(
            (string) $website['url'],
            (string) ($website['method'] ?? 'GET'),
            (int) ($website['timeout_ms'] ?? 10000),
            (int) ($website['expected_status'] ?? 200),
            $website['keyword'] ?? null
        );

        $pdo = DB::pdo();
        $id = cuid();
        $pdo->prepare(
            'INSERT INTO monitor_logs (id, website_id, status, status_code, response_ms, error_message, checked_at)
             VALUES (:id, :wid, :status, :code, :ms, :err, UTC_TIMESTAMP(3))'
        )->execute([
            'id' => $id,
            'wid' => $website['id'],
            'status' => $result['status'],
            'code' => $result['status_code'],
            'ms' => $result['response_ms'],
            'err' => $result['error'],
        ]);

        $prev = (string) ($website['current_status'] ?? 'UNKNOWN');
        $pdo->prepare(
            'UPDATE websites
             SET current_status = :status,
                 last_checked_at = UTC_TIMESTAMP(3),
                 last_response_ms = :ms,
                 last_status_code = :code,
                 last_error = :err,
                 updated_at = UTC_TIMESTAMP(3)
             WHERE id = :id'
        )->execute([
            'status' => $result['status'],
            'ms' => $result['response_ms'],
            'code' => $result['status_code'],
            'err' => $result['error'],
            'id' => $website['id'],
        ]);

        // Incident open/close
        if ($result['status'] === 'DOWN' && $prev !== 'DOWN') {
            $pdo->prepare(
                'INSERT INTO incidents (id, website_id, status, started_at, summary)
                 VALUES (:id, :wid, \'DOWN\', UTC_TIMESTAMP(3), :summary)'
            )->execute([
                'id' => cuid(),
                'wid' => $website['id'],
                'summary' => $result['error'] ?: 'Site reported DOWN',
            ]);
        } elseif ($result['status'] === 'UP' && $prev === 'DOWN') {
            $pdo->prepare(
                'UPDATE incidents SET resolved_at = UTC_TIMESTAMP(3)
                 WHERE website_id = :wid AND resolved_at IS NULL'
            )->execute(['wid' => $website['id']]);
        }

        return $result;
    }

    /** @return array{total:int,up:int,down:int,degraded:int,uptime:float,avg_ms:?int} */
    public static function uptime(string $websiteId, int $hours): array
    {
        $from = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$hours} hours")
            ->format('Y-m-d H:i:s');

        $stmt = DB::pdo()->prepare(
            'SELECT status, COUNT(*) cnt, AVG(response_ms) avg_ms
             FROM monitor_logs
             WHERE website_id = :id AND checked_at >= :from
             GROUP BY status'
        );
        $stmt->execute(['id' => $websiteId, 'from' => $from]);

        $total = $up = $down = $degraded = 0;
        $sum = 0.0;
        $n = 0;
        foreach ($stmt->fetchAll() as $row) {
            $c = (int) $row['cnt'];
            $total += $c;
            match ($row['status']) {
                'UP' => $up += $c,
                'DOWN' => $down += $c,
                'DEGRADED' => $degraded += $c,
                default => null,
            };
            if ($row['avg_ms'] !== null) {
                $sum += ((float) $row['avg_ms']) * $c;
                $n += $c;
            }
        }

        $uptime = $total === 0 ? 100.0 : round((($up + $degraded) / $total) * 10000) / 100;

        return [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'degraded' => $degraded,
            'uptime' => $uptime,
            'avg_ms' => $n > 0 ? (int) round($sum / $n) : null,
        ];
    }
}
