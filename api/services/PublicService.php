<?php
declare(strict_types=1);

final class PublicService
{
    /** @param list<string> $statuses */
    private static function overallFromStatuses(array $statuses): string
    {
        if ($statuses === []) {
            return 'UNKNOWN';
        }
        if (in_array('DOWN', $statuses, true)) {
            return 'DOWN';
        }
        if (in_array('DEGRADED', $statuses, true)) {
            return 'DEGRADED';
        }
        if (count(array_filter($statuses, static fn ($s) => $s === 'UNKNOWN')) === count($statuses)) {
            return 'UNKNOWN';
        }
        return 'UP';
    }

    public static function getPublicStatus(): array
    {
        $pdo = Database::connection();
        $websites = $pdo->query(
            'SELECT * FROM websites WHERE is_public = 1 ORDER BY name ASC'
        )->fetchAll();

        $items = [];
        foreach ($websites as $w) {
            $uptime = UptimeService::getMultiPeriodUptime($w['id']);

            $incStmt = $pdo->prepare(
                'SELECT * FROM incidents
                 WHERE website_id = :wid AND resolved_at IS NULL
                 ORDER BY started_at DESC LIMIT 5'
            );
            $incStmt->execute(['wid' => $w['id']]);
            $openIncidents = array_map([WebsiteService::class, 'incidentToApi'], $incStmt->fetchAll());

            $items[] = [
                'id' => $w['id'],
                'name' => $w['name'],
                'slug' => $w['slug'],
                'url' => $w['url'],
                'description' => $w['description'],
                'currentStatus' => $w['current_status'],
                'lastCheckedAt' => Response::iso($w['last_checked_at'] ?? null),
                'lastResponseMs' => Response::intOrNull($w['last_response_ms'] ?? null),
                'lastStatusCode' => Response::intOrNull($w['last_status_code'] ?? null),
                'isStale' => UptimeService::isStale(
                    $w['last_checked_at'] ?? null,
                    (int) $w['interval_seconds'],
                ),
                'uptime' => [
                    'h24' => $uptime['h24']['uptimePercent'],
                    'd7' => $uptime['d7']['uptimePercent'],
                    'd30' => $uptime['d30']['uptimePercent'],
                    'd90' => $uptime['d90']['uptimePercent'],
                ],
                'avgResponse' => [
                    'h24' => $uptime['h24']['avgResponseMs'],
                    'd7' => $uptime['d7']['avgResponseMs'],
                    'd30' => $uptime['d30']['avgResponseMs'],
                    'd90' => $uptime['d90']['avgResponseMs'],
                ],
                'history' => UptimeService::getDailyHistory($w['id'], 90),
                'openIncidents' => $openIncidents,
            ];
        }

        return [
            'overall' => self::overallFromStatuses(array_column($items, 'currentStatus')),
            'websites' => $items,
            'generatedAt' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    public static function getPublicSiteStatus(string $slug): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM websites WHERE slug = :slug AND is_public = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $website = $stmt->fetch();
        if (!$website) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }

        $incStmt = $pdo->prepare(
            'SELECT * FROM incidents WHERE website_id = :wid ORDER BY started_at DESC LIMIT 20'
        );
        $incStmt->execute(['wid' => $website['id']]);

        return [
            'website' => [
                'id' => $website['id'],
                'name' => $website['name'],
                'slug' => $website['slug'],
                'url' => $website['url'],
                'description' => $website['description'],
                'currentStatus' => $website['current_status'],
                'lastCheckedAt' => Response::iso($website['last_checked_at'] ?? null),
                'lastResponseMs' => Response::intOrNull($website['last_response_ms'] ?? null),
                'lastStatusCode' => Response::intOrNull($website['last_status_code'] ?? null),
                'isStale' => UptimeService::isStale(
                    $website['last_checked_at'] ?? null,
                    (int) $website['interval_seconds'],
                ),
            ],
            'uptime' => UptimeService::getMultiPeriodUptime($website['id']),
            'sparkline' => UptimeService::getSparklineData($website['id'], 48),
            'history' => UptimeService::getDailyHistory($website['id'], 90),
            'incidents' => array_map([WebsiteService::class, 'incidentToApi'], $incStmt->fetchAll()),
            'generatedAt' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}
