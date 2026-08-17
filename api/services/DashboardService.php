<?php
declare(strict_types=1);

final class DashboardService
{
    public static function get(): array
    {
        $pdo = Database::connection();
        $since24h = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-24 hours')
            ->format('Y-m-d H:i:s.v');

        $totalSites = (int) $pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn();

        $statusCounts = ['UP' => 0, 'DOWN' => 0, 'UNKNOWN' => 0, 'DEGRADED' => 0];
        foreach ($pdo->query('SELECT current_status, COUNT(*) AS cnt FROM websites GROUP BY current_status') as $row) {
            $statusCounts[$row['current_status']] = (int) $row['cnt'];
        }

        $avg = $pdo->query(
            'SELECT AVG(last_response_ms) AS avg_ms FROM websites WHERE last_response_ms IS NOT NULL'
        )->fetch();
        $avgResponseMs = isset($avg['avg_ms']) && $avg['avg_ms'] !== null
            ? (int) round((float) $avg['avg_ms'])
            : null;

        $incStmt = $pdo->query(
            'SELECT i.*, w.id AS w_id, w.name AS w_name, w.slug AS w_slug
             FROM incidents i
             INNER JOIN websites w ON w.id = i.website_id
             ORDER BY i.started_at DESC
             LIMIT 10'
        );
        $recentIncidents = array_map([WebsiteService::class, 'incidentToApi'], $incStmt->fetchAll());

        $checksStmt = $pdo->prepare('SELECT COUNT(*) FROM monitor_logs WHERE checked_at >= :from');
        $checksStmt->execute(['from' => $since24h]);
        $checksLast24h = (int) $checksStmt->fetchColumn();

        $logGroups = $pdo->prepare(
            'SELECT status, COUNT(*) AS cnt FROM monitor_logs WHERE checked_at >= :from GROUP BY status'
        );
        $logGroups->execute(['from' => $since24h]);
        $up = 0;
        $degraded = 0;
        $totalLogs = 0;
        foreach ($logGroups->fetchAll() as $g) {
            $cnt = (int) $g['cnt'];
            $totalLogs += $cnt;
            if ($g['status'] === 'UP') {
                $up += $cnt;
            }
            if ($g['status'] === 'DEGRADED') {
                $degraded += $cnt;
            }
        }

        $websites = $pdo->query(
            'SELECT id, last_checked_at, interval_seconds, is_active FROM websites'
        )->fetchAll();
        $staleCount = 0;
        foreach ($websites as $w) {
            if (
                Response::bool($w['is_active']) &&
                UptimeService::isStale($w['last_checked_at'] ?? null, (int) $w['interval_seconds'])
            ) {
                $staleCount++;
            }
        }

        return [
            'totalSites' => $totalSites,
            'statusCounts' => $statusCounts,
            'avgResponseMs' => $avgResponseMs,
            'overallUptime24h' => UptimeService::calculateUptimePercent($totalLogs, $up, $degraded),
            'recentIncidents' => $recentIncidents,
            'checksLast24h' => $checksLast24h,
            'staleCount' => $staleCount,
        ];
    }
}
