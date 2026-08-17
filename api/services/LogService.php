<?php
declare(strict_types=1);

final class LogService
{
    /** @param array<string, mixed> $query */
    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $allowedSort = [
            'checkedAt' => 'l.checked_at',
            'responseMs' => 'l.response_ms',
            'status' => 'l.status',
        ];
        $sortBy = $allowedSort[$query['sortBy'] ?? 'checkedAt'] ?? 'l.checked_at';
        $sortOrder = strtolower((string) ($query['sortOrder'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        [$whereSql, $params] = self::buildWhere($query);

        $pdo = Database::connection();
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM monitor_logs l
             INNER JOIN websites w ON w.id = l.website_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT l.*, w.id AS w_id, w.name AS w_name, w.slug AS w_slug, w.url AS w_url
             FROM monitor_logs l
             INNER JOIN websites w ON w.id = l.website_id
             WHERE {$whereSql}
             ORDER BY {$sortBy} {$sortOrder}
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $items = array_map(static function (array $r): array {
            return [
                'id' => $r['id'],
                'websiteId' => $r['website_id'],
                'status' => $r['status'],
                'statusCode' => Response::intOrNull($r['status_code']),
                'responseMs' => Response::intOrNull($r['response_ms']),
                'errorMessage' => $r['error_message'],
                'checkedAt' => Response::iso($r['checked_at']),
                'website' => [
                    'id' => $r['w_id'],
                    'name' => $r['w_name'],
                    'slug' => $r['w_slug'],
                    'url' => $r['w_url'],
                ],
            ];
        }, $stmt->fetchAll());

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max($limit, 1)),
        ];
    }

    /** @param array<string, mixed> $query */
    public static function exportCsv(array $query): string
    {
        [$whereSql, $params] = self::buildWhere($query);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT l.*, w.name AS w_name, w.slug AS w_slug, w.url AS w_url
             FROM monitor_logs l
             INNER JOIN websites w ON w.id = l.website_id
             WHERE {$whereSql}
             ORDER BY l.checked_at DESC
             LIMIT 10000"
        );
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $headers = [
            'id', 'websiteName', 'websiteSlug', 'url', 'status',
            'statusCode', 'responseMs', 'errorMessage', 'checkedAt',
        ];

        $escape = static function ($v): string {
            $s = $v === null ? '' : (string) $v;
            if (str_contains($s, ',') || str_contains($s, '"') || str_contains($s, "\n")) {
                return '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        };

        $lines = [implode(',', $headers)];
        foreach ($logs as $l) {
            $lines[] = implode(',', array_map($escape, [
                $l['id'],
                $l['w_name'],
                $l['w_slug'],
                $l['w_url'],
                $l['status'],
                $l['status_code'] ?? '',
                $l['response_ms'] ?? '',
                $l['error_message'] ?? '',
                Response::iso($l['checked_at']),
            ]));
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildWhere(array $query): array
    {
        $where = [];
        $params = [];

        if (!empty($query['websiteId'])) {
            $where[] = 'l.website_id = :website_id';
            $params['website_id'] = $query['websiteId'];
        }
        if (!empty($query['status'])) {
            $where[] = 'l.status = :status';
            $params['status'] = $query['status'];
        }
        if (!empty($query['from'])) {
            $where[] = 'l.checked_at >= :from';
            $params['from'] = self::toMysql((string) $query['from']);
        }
        if (!empty($query['to'])) {
            $where[] = 'l.checked_at <= :to';
            $params['to'] = self::toMysql((string) $query['to']);
        }
        if (!empty($query['search'])) {
            $where[] = '(l.error_message LIKE :search OR w.name LIKE :search)';
            $params['search'] = '%' . $query['search'] . '%';
        }

        return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
    }

    private static function toMysql(string $iso): string
    {
        $dt = new DateTimeImmutable($iso);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
