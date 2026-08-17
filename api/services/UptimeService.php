<?php
declare(strict_types=1);

final class UptimeService
{
    public static function isStale(?string $lastCheckedAt, int $intervalSeconds, ?float $multiplier = null): bool
    {
        $mult = $multiplier ?? (float) Env::getString('STALE_MULTIPLIER', '2');
        if ($lastCheckedAt === null || $lastCheckedAt === '') {
            return true;
        }
        try {
            $last = new DateTimeImmutable($lastCheckedAt, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return true;
        }
        $thresholdMs = $intervalSeconds * $mult * 1000;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return ($now->getTimestamp() * 1000 + (int) $now->format('v')) -
            ($last->getTimestamp() * 1000 + (int) $last->format('v')) > $thresholdMs;
    }

    public static function calculateUptimePercent(int $total, int $up, int $degraded): float
    {
        if ($total === 0) {
            return 100.0;
        }
        return round((($up + $degraded) / $total) * 10000) / 100;
    }

    /** @return array{total:int,up:int,down:int,degraded:int,unknown:int,uptimePercent:float,avgResponseMs:?int} */
    public static function getUptimeStats(string $websiteId, ?int $hours = null, ?int $days = null): array
    {
        $from = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($hours !== null) {
            $from = $from->modify("-{$hours} hours");
        } elseif ($days !== null) {
            $from = $from->modify("-{$days} days");
        }
        $fromStr = $from->format('Y-m-d H:i:s.v');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS cnt, AVG(response_ms) AS avg_ms
             FROM monitor_logs
             WHERE website_id = :wid AND checked_at >= :from
             GROUP BY status'
        );
        $stmt->execute(['wid' => $websiteId, 'from' => $fromStr]);
        $rows = $stmt->fetchAll();

        $total = 0;
        $up = 0;
        $down = 0;
        $degraded = 0;
        $unknown = 0;
        $responseSum = 0.0;
        $responseCount = 0;

        foreach ($rows as $row) {
            $count = (int) $row['cnt'];
            $total += $count;
            switch ($row['status']) {
                case 'UP':
                    $up += $count;
                    break;
                case 'DOWN':
                    $down += $count;
                    break;
                case 'DEGRADED':
                    $degraded += $count;
                    break;
                default:
                    $unknown += $count;
            }
            if ($row['avg_ms'] !== null) {
                $responseSum += ((float) $row['avg_ms']) * $count;
                $responseCount += $count;
            }
        }

        return [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'degraded' => $degraded,
            'unknown' => $unknown,
            'uptimePercent' => self::calculateUptimePercent($total, $up, $degraded),
            'avgResponseMs' => $responseCount > 0 ? (int) round($responseSum / $responseCount) : null,
        ];
    }

    /** @return array{h24:array,d7:array,d30:array,d90:array} */
    public static function getMultiPeriodUptime(string $websiteId): array
    {
        return [
            'h24' => self::getUptimeStats($websiteId, 24, null),
            'd7' => self::getUptimeStats($websiteId, null, 7),
            'd30' => self::getUptimeStats($websiteId, null, 30),
            'd90' => self::getUptimeStats($websiteId, null, 90),
        ];
    }

    /** @return list<array{checkedAt:string,responseMs:?int,status:string}> */
    public static function getResponseTimeSeries(string $websiteId, int $days = 7): array
    {
        $from = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s.v');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT checked_at, response_ms, status
             FROM monitor_logs
             WHERE website_id = :wid AND checked_at >= :from
             ORDER BY checked_at ASC
             LIMIT 5000'
        );
        $stmt->execute(['wid' => $websiteId, 'from' => $from]);

        return array_map(static function (array $r): array {
            return [
                'checkedAt' => Response::iso($r['checked_at']),
                'responseMs' => Response::intOrNull($r['response_ms']),
                'status' => $r['status'],
            ];
        }, $stmt->fetchAll());
    }

    /** @return list<array{t:string,ms:?int,s:string}> */
    public static function getSparklineData(string $websiteId, int $points = 48): array
    {
        $from = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-24 hours')
            ->format('Y-m-d H:i:s.v');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT checked_at, response_ms, status
             FROM monitor_logs
             WHERE website_id = :wid AND checked_at >= :from
             ORDER BY checked_at ASC'
        );
        $stmt->execute(['wid' => $websiteId, 'from' => $from]);
        $logs = $stmt->fetchAll();

        if ($logs === []) {
            return [];
        }

        $map = static function (array $l): array {
            return [
                't' => Response::iso($l['checked_at']),
                'ms' => Response::intOrNull($l['response_ms']),
                's' => $l['status'],
            ];
        };

        if (count($logs) <= $points) {
            return array_map($map, $logs);
        }

        $step = (int) ceil(count($logs) / $points);
        $sampled = [];
        for ($i = 0; $i < count($logs); $i += $step) {
            $sampled[] = $map($logs[$i]);
        }
        return $sampled;
    }

    /** @return list<array{date:string,segment:string}> */
    public static function getDailyHistory(string $websiteId, int $days = 90): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $start = $now->setTime(0, 0, 0)->modify('-' . ($days - 1) . ' days');
        $fromStr = $start->format('Y-m-d H:i:s.v');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT checked_at, status FROM monitor_logs
             WHERE website_id = :wid AND checked_at >= :from
             ORDER BY checked_at ASC'
        );
        $stmt->execute(['wid' => $websiteId, 'from' => $fromStr]);
        $logs = $stmt->fetchAll();

        $rank = ['DOWN' => 4, 'DEGRADED' => 3, 'UNKNOWN' => 2, 'UP' => 1];
        $byDay = [];
        foreach ($logs as $log) {
            $key = (new DateTimeImmutable($log['checked_at'], new DateTimeZone('UTC')))->format('Y-m-d');
            $status = $log['status'];
            if (!isset($byDay[$key]) || $rank[$status] > $rank[$byDay[$key]]) {
                $byDay[$key] = $status;
            }
        }

        $toSegment = static function (?string $status): string {
            return match ($status) {
                'UP' => 'up',
                'DOWN' => 'down',
                'DEGRADED' => 'degraded',
                'UNKNOWN' => 'unknown',
                default => 'empty',
            };
        };

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->modify("+{$i} days");
            $key = $d->format('Y-m-d');
            $result[] = [
                'date' => $key,
                'segment' => $toSegment($byDay[$key] ?? null),
            ];
        }
        return $result;
    }
}
