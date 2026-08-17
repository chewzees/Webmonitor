<?php
declare(strict_types=1);

final class MonitorService
{
    private const DEGRADED_THRESHOLD_MS = 3000;
    private const CONCURRENCY = 10;

    /**
     * @param array<string, mixed> $target
     * @return array{status:string,statusCode:?int,responseMs:?int,errorMessage:?string}
     */
    public static function checkWebsite(array $target): array
    {
        $started = (int) (microtime(true) * 1000);
        $timeoutSec = max(1, (int) ceil(((int) $target['timeout_ms']) / 1000));
        $method = strtoupper((string) ($target['method'] ?? 'GET'));
        $url = (string) $target['url'];
        $expected = (int) ($target['expected_status'] ?? 200);
        $keyword = $target['keyword'] ?? null;
        $headers = self::parseHeaders($target['headers_json'] ?? null);
        $headers['User-Agent'] = $headers['User-Agent'] ?? 'WebMonitor/1.0';
        $headers['Accept'] = $headers['Accept'] ?? '*/*';

        try {
            if (function_exists('curl_init')) {
                $result = self::checkWithCurl($url, $method, $headers, $timeoutSec, $keyword);
            } else {
                $result = self::checkWithStream($url, $method, $headers, $timeoutSec, $keyword);
            }

            $responseMs = (int) (microtime(true) * 1000) - $started;
            if ($result['responseMs'] === null) {
                $result['responseMs'] = $responseMs;
            }

            $status = self::resolveStatus(
                $result['statusCode'],
                $expected,
                $result['keywordOk'],
                $result['responseMs'],
                false,
            );

            $errorMessage = null;
            if ($result['statusCode'] !== $expected) {
                $errorMessage = "Expected status {$expected}, got {$result['statusCode']}";
            } elseif (!$result['keywordOk']) {
                $errorMessage = 'Keyword "' . $keyword . '" not found in response body';
            } elseif ($status === 'DEGRADED') {
                $errorMessage = 'Slow response: ' . $result['responseMs'] . 'ms';
            }

            return [
                'status' => $status,
                'statusCode' => $result['statusCode'],
                'responseMs' => $result['responseMs'],
                'errorMessage' => $errorMessage,
            ];
        } catch (Throwable $e) {
            $responseMs = (int) (microtime(true) * 1000) - $started;
            $message = $e->getMessage();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
                $message = 'Timeout after ' . ((int) $target['timeout_ms']) . 'ms';
            }
            return [
                'status' => 'DOWN',
                'statusCode' => null,
                'responseMs' => $responseMs,
                'errorMessage' => $message,
            ];
        }
    }

    public static function resolveStatus(
        ?int $statusCode,
        int $expectedStatus,
        bool $keywordOk,
        ?int $responseMs,
        bool $hadError,
    ): string {
        if ($hadError || $statusCode === null) {
            return 'DOWN';
        }
        if ($statusCode !== $expectedStatus || !$keywordOk) {
            return 'DOWN';
        }
        if ($responseMs !== null && $responseMs > self::DEGRADED_THRESHOLD_MS) {
            return 'DEGRADED';
        }
        return 'UP';
    }

    /** @return array<string, string> */
    private static function parseHeaders(?string $headersJson): array
    {
        if (!$headersJson) {
            return [];
        }
        try {
            $parsed = json_decode($headersJson, true);
            if (!is_array($parsed) || array_is_list($parsed)) {
                return [];
            }
            $out = [];
            foreach ($parsed as $k => $v) {
                if (is_string($v)) {
                    $out[(string) $k] = $v;
                }
            }
            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array{statusCode:?int,responseMs:?int,keywordOk:bool}
     */
    private static function checkWithCurl(
        string $url,
        string $method,
        array $headers,
        int $timeoutSec,
        mixed $keyword,
    ): array {
        $ch = curl_init($url);
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException($error !== '' ? $error : 'cURL error ' . $errno);
        }

        $keywordOk = true;
        if ($keyword && $method !== 'HEAD' && is_string($body)) {
            $keywordOk = str_contains($body, (string) $keyword);
        }

        return [
            'statusCode' => $statusCode > 0 ? (int) $statusCode : null,
            'responseMs' => $totalTime !== false ? (int) round($totalTime * 1000) : null,
            'keywordOk' => $keywordOk,
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{statusCode:?int,responseMs:?int,keywordOk:bool}
     */
    private static function checkWithStream(
        string $url,
        string $method,
        array $headers,
        int $timeoutSec,
        mixed $keyword,
    ): array {
        $headerStr = '';
        foreach ($headers as $k => $v) {
            $headerStr .= "{$k}: {$v}\r\n";
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerStr,
                'timeout' => $timeoutSec,
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        $statusCode = null;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $statusCode = (int) $m[1];
        }
        if ($body === false && $statusCode === null) {
            throw new RuntimeException('Request failed');
        }

        $keywordOk = true;
        if ($keyword && $method !== 'HEAD' && is_string($body)) {
            $keywordOk = str_contains($body, (string) $keyword);
        }

        return [
            'statusCode' => $statusCode,
            'responseMs' => null,
            'keywordOk' => $keywordOk,
        ];
    }

    /** @param array<string, mixed> $website */
    public static function processCheckResult(array $website, array $result): array
    {
        $pdo = Database::connection();
        $previous = (string) $website['current_status'];
        $statusChanged = $previous !== $result['status'];

        $logId = Cuid::generate();
        $pdo->prepare(
            'INSERT INTO monitor_logs (id, website_id, status, status_code, response_ms, error_message, checked_at)
             VALUES (:id, :wid, :status, :code, :ms, :err, UTC_TIMESTAMP(3))'
        )->execute([
            'id' => $logId,
            'wid' => $website['id'],
            'status' => $result['status'],
            'code' => $result['statusCode'],
            'ms' => $result['responseMs'],
            'err' => $result['errorMessage'],
        ]);

        $pdo->prepare(
            'UPDATE websites SET
                current_status = :status,
                last_checked_at = UTC_TIMESTAMP(3),
                last_response_ms = :ms,
                last_status_code = :code,
                last_error = :err,
                updated_at = UTC_TIMESTAMP(3)
             WHERE id = :id'
        )->execute([
            'status' => $result['status'],
            'ms' => $result['responseMs'],
            'code' => $result['statusCode'],
            'err' => $result['errorMessage'],
            'id' => $website['id'],
        ]);

        if ($statusChanged) {
            self::handleIncidents($website['id'], $previous, $result['status'], $result['errorMessage']);
            EventBus::publish('status.changed', [
                'websiteId' => $website['id'],
                'name' => $website['name'],
                'slug' => $website['slug'],
                'from' => $previous,
                'to' => $result['status'],
            ]);
            TelegramService::notifyStatusChange($website, $previous, $result);
        }

        EventBus::publish('check.completed', [
            'websiteId' => $website['id'],
            'name' => $website['name'],
            'status' => $result['status'],
            'statusCode' => $result['statusCode'],
            'responseMs' => $result['responseMs'],
            'errorMessage' => $result['errorMessage'],
        ]);

        $stmt = $pdo->prepare('SELECT * FROM websites WHERE id = :id');
        $stmt->execute(['id' => $website['id']]);
        return $stmt->fetch() ?: $website;
    }

    private static function handleIncidents(
        string $websiteId,
        string $previous,
        string $next,
        ?string $errorMessage,
    ): void {
        $pdo = Database::connection();
        $isBad = static fn (string $s): bool => $s === 'DOWN' || $s === 'DEGRADED';
        $isGood = static fn (string $s): bool => $s === 'UP' || $s === 'UNKNOWN';

        if ($isBad($next) && !$isBad($previous)) {
            $pdo->prepare(
                'INSERT INTO incidents (id, website_id, status, started_at, summary)
                 VALUES (:id, :wid, :status, UTC_TIMESTAMP(3), :summary)'
            )->execute([
                'id' => Cuid::generate(),
                'wid' => $websiteId,
                'status' => $next,
                'summary' => $errorMessage ?? "Status changed to {$next}",
            ]);
        } elseif ($isGood($next) && $isBad($previous)) {
            $pdo->prepare(
                'UPDATE incidents SET resolved_at = UTC_TIMESTAMP(3)
                 WHERE website_id = :wid AND resolved_at IS NULL'
            )->execute(['wid' => $websiteId]);
        } elseif ($isBad($next) && $isBad($previous) && $next !== $previous) {
            $pdo->prepare(
                'UPDATE incidents SET resolved_at = UTC_TIMESTAMP(3)
                 WHERE website_id = :wid AND resolved_at IS NULL'
            )->execute(['wid' => $websiteId]);
            $pdo->prepare(
                'INSERT INTO incidents (id, website_id, status, started_at, summary)
                 VALUES (:id, :wid, :status, UTC_TIMESTAMP(3), :summary)'
            )->execute([
                'id' => Cuid::generate(),
                'wid' => $websiteId,
                'status' => $next,
                'summary' => $errorMessage ?? "Status changed to {$next}",
            ]);
        }
    }

    /** @param array<string, mixed> $website */
    public static function runCheckForWebsite(array $website): array
    {
        $result = self::checkWebsite([
            'id' => $website['id'],
            'url' => $website['url'],
            'method' => $website['method'],
            'timeout_ms' => (int) $website['timeout_ms'],
            'expected_status' => (int) $website['expected_status'],
            'keyword' => $website['keyword'],
            'headers_json' => $website['headers_json'],
        ]);
        return self::processCheckResult($website, $result);
    }

    /** @return list<array<string, mixed>> */
    public static function getDueWebsites(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM websites WHERE is_active = 1');
        $active = $stmt->fetchAll();
        $now = time();
        $due = [];
        foreach ($active as $w) {
            if (empty($w['last_checked_at'])) {
                $due[] = $w;
                continue;
            }
            $last = strtotime($w['last_checked_at'] . ' UTC');
            $dueAt = $last + ((int) $w['interval_seconds']);
            if ($dueAt <= $now) {
                $due[] = $w;
            }
        }
        return $due;
    }

    public static function runDueChecks(): int
    {
        $due = self::getDueWebsites();
        foreach ($due as $website) {
            try {
                self::runCheckForWebsite($website);
            } catch (Throwable) {
                // continue
            }
        }
        return count($due);
    }

    public static function runAllActiveChecks(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM websites WHERE is_active = 1');
        $active = $stmt->fetchAll();
        foreach ($active as $website) {
            try {
                self::runCheckForWebsite($website);
            } catch (Throwable) {
                // continue
            }
        }
        return count($active);
    }

    public static function purgeOldLogs(?int $days = null): int
    {
        $days = max(1, (int) ($days ?? Env::getInt('LOG_RETENTION_DAYS', 90)));
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "DELETE FROM monitor_logs WHERE checked_at < (UTC_TIMESTAMP(3) - INTERVAL {$days} DAY)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
