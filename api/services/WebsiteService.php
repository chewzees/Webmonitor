<?php
declare(strict_types=1);

final class WebsiteService
{
    /** @param array<string, mixed> $row */
    public static function toApi(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'url' => $row['url'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'method' => $row['method'],
            'intervalSeconds' => (int) $row['interval_seconds'],
            'timeoutMs' => (int) $row['timeout_ms'],
            'expectedStatus' => (int) $row['expected_status'],
            'keyword' => $row['keyword'],
            'headersJson' => $row['headers_json'],
            'isActive' => Response::bool($row['is_active']),
            'isPublic' => Response::bool($row['is_public']),
            'currentStatus' => $row['current_status'],
            'lastCheckedAt' => Response::iso($row['last_checked_at'] ?? null),
            'lastResponseMs' => Response::intOrNull($row['last_response_ms'] ?? null),
            'lastStatusCode' => Response::intOrNull($row['last_status_code'] ?? null),
            'lastError' => $row['last_error'] ?? null,
            'createdAt' => Response::iso($row['created_at']),
            'updatedAt' => Response::iso($row['updated_at']),
            'isStale' => UptimeService::isStale(
                $row['last_checked_at'] ?? null,
                (int) $row['interval_seconds'],
            ),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function incidentToApi(array $row): array
    {
        $out = [
            'id' => $row['id'],
            'websiteId' => $row['website_id'],
            'status' => $row['status'],
            'startedAt' => Response::iso($row['started_at']),
            'resolvedAt' => Response::iso($row['resolved_at'] ?? null),
            'summary' => $row['summary'] ?? null,
        ];
        if (isset($row['w_id'])) {
            $out['website'] = [
                'id' => $row['w_id'],
                'name' => $row['w_name'],
                'slug' => $row['w_slug'],
            ];
        }
        return $out;
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 80);
        return $slug !== '' ? $slug : 'website';
    }

    public static function ensureUniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base;
        $i = 2;
        while (self::slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public static function slugExists(string $slug, ?string $excludeId = null): bool
    {
        $pdo = Database::connection();
        if ($excludeId) {
            $stmt = $pdo->prepare('SELECT id FROM websites WHERE slug = :slug AND id != :id LIMIT 1');
            $stmt->execute(['slug' => $slug, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM websites WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
        }
        return (bool) $stmt->fetch();
    }

    public static function findById(string $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM websites WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $query */
    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $allowedSort = [
            'name' => 'name',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            'lastCheckedAt' => 'last_checked_at',
            'currentStatus' => 'current_status',
            'intervalSeconds' => 'interval_seconds',
        ];
        $sortBy = $allowedSort[$query['sortBy'] ?? 'name'] ?? 'name';
        $sortOrder = strtolower((string) ($query['sortOrder'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];

        if (!empty($query['search'])) {
            $where[] = '(name LIKE :search OR url LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $query['search'] . '%';
        }
        if (!empty($query['status'])) {
            $where[] = 'current_status = :status';
            $params['status'] = $query['status'];
        }
        if (isset($query['isActive']) && $query['isActive'] !== '' && $query['isActive'] !== null) {
            $active = $query['isActive'];
            if (is_string($active)) {
                $active = $active === 'true';
            }
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $active ? 1 : 0;
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $pdo = Database::connection();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT * FROM websites WHERE {$whereSql} ORDER BY {$sortBy} {$sortOrder} LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $items = array_map([self::class, 'toApi'], $stmt->fetchAll());

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max($limit, 1)),
        ];
    }

    public static function get(string $id): array
    {
        $row = self::findById($id);
        if (!$row) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }
        return self::toApi($row);
    }

    /** @param array<string, mixed> $input */
    public static function create(array $input, ?string $userId = null, ?array $meta = null): array
    {
        if (!empty($input['headersJson'])) {
            json_decode((string) $input['headersJson']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AppException('headersJson must be valid JSON', 400, 'VALIDATION_ERROR');
            }
        }

        $baseSlug = $input['slug'] ?? self::slugify((string) $input['name']);
        $slug = self::ensureUniqueSlug($baseSlug);
        $id = Cuid::generate();
        $pdo = Database::connection();

        $pdo->prepare(
            'INSERT INTO websites (
                id, name, url, slug, description, method, interval_seconds, timeout_ms,
                expected_status, keyword, headers_json, is_active, is_public,
                current_status, created_at, updated_at
             ) VALUES (
                :id, :name, :url, :slug, :description, :method, :interval_seconds, :timeout_ms,
                :expected_status, :keyword, :headers_json, :is_active, :is_public,
                \'UNKNOWN\', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
             )'
        )->execute([
            'id' => $id,
            'name' => $input['name'],
            'url' => $input['url'],
            'slug' => $slug,
            'description' => $input['description'] ?? null,
            'method' => $input['method'] ?? 'GET',
            'interval_seconds' => $input['intervalSeconds'] ?? 60,
            'timeout_ms' => $input['timeoutMs'] ?? 10000,
            'expected_status' => $input['expectedStatus'] ?? 200,
            'keyword' => $input['keyword'] ?? null,
            'headers_json' => $input['headersJson'] ?? null,
            'is_active' => ($input['isActive'] ?? true) ? 1 : 0,
            'is_public' => ($input['isPublic'] ?? true) ? 1 : 0,
        ]);

        AuditService::write([
            'userId' => $userId,
            'action' => 'website.create',
            'entityType' => 'Website',
            'entityId' => $id,
            'metadata' => ['name' => $input['name'], 'url' => $input['url']],
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        EventBus::publish('website.updated', ['action' => 'create', 'websiteId' => $id]);

        return self::get($id);
    }

    /** @param array<string, mixed> $input */
    public static function update(string $id, array $input, ?string $userId = null, ?array $meta = null): array
    {
        $existing = self::findById($id);
        if (!$existing) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }

        if (!empty($input['headersJson'])) {
            json_decode((string) $input['headersJson']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AppException('headersJson must be valid JSON', 400, 'VALIDATION_ERROR');
            }
        }

        if (isset($input['slug']) && $input['slug'] !== $existing['slug']) {
            if (self::slugExists((string) $input['slug'], $id)) {
                throw new AppException('Slug already in use', 409, 'CONFLICT');
            }
        } elseif (isset($input['name']) && !isset($input['slug'])) {
            unset($input['slug']);
        }

        $map = [
            'name' => 'name',
            'url' => 'url',
            'slug' => 'slug',
            'description' => 'description',
            'method' => 'method',
            'intervalSeconds' => 'interval_seconds',
            'timeoutMs' => 'timeout_ms',
            'expectedStatus' => 'expected_status',
            'keyword' => 'keyword',
            'headersJson' => 'headers_json',
            'isActive' => 'is_active',
            'isPublic' => 'is_public',
        ];

        $fields = [];
        $params = ['id' => $id];
        foreach ($map as $api => $col) {
            if (!array_key_exists($api, $input)) {
                continue;
            }
            $val = $input[$api];
            if ($api === 'isActive' || $api === 'isPublic') {
                $val = $val ? 1 : 0;
            }
            $fields[] = "{$col} = :{$col}";
            $params[$col] = $val;
        }

        if ($fields !== []) {
            $fields[] = 'updated_at = UTC_TIMESTAMP(3)';
            $sql = 'UPDATE websites SET ' . implode(', ', $fields) . ' WHERE id = :id';
            Database::connection()->prepare($sql)->execute($params);
        }

        AuditService::write([
            'userId' => $userId,
            'action' => 'website.update',
            'entityType' => 'Website',
            'entityId' => $id,
            'metadata' => $input,
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        EventBus::publish('website.updated', ['action' => 'update', 'websiteId' => $id]);

        return self::get($id);
    }

    public static function delete(string $id, ?string $userId = null, ?array $meta = null): void
    {
        $existing = self::findById($id);
        if (!$existing) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }

        Database::connection()->prepare('DELETE FROM websites WHERE id = :id')->execute(['id' => $id]);

        AuditService::write([
            'userId' => $userId,
            'action' => 'website.delete',
            'entityType' => 'Website',
            'entityId' => $id,
            'metadata' => ['name' => $existing['name']],
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        EventBus::publish('website.updated', ['action' => 'delete', 'websiteId' => $id]);
    }

    public static function checkNow(string $id): array
    {
        $website = self::findById($id);
        if (!$website) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }
        $updated = MonitorService::runCheckForWebsite($website);
        return self::toApi($updated);
    }

    public static function checkAll(): array
    {
        $count = MonitorService::runAllActiveChecks();
        return ['checked' => $count];
    }

    /** @param array{ids:list<string>,action:string} $input */
    public static function bulkAction(array $input, ?string $userId = null, ?array $meta = null): array
    {
        $pdo = Database::connection();
        $ids = $input['ids'];
        if ($ids === []) {
            throw new AppException('No websites found for given ids', 404, 'NOT_FOUND');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $websites = $stmt->fetchAll();
        if ($websites === []) {
            throw new AppException('No websites found for given ids', 404, 'NOT_FOUND');
        }

        $affected = 0;
        switch ($input['action']) {
            case 'activate':
                $pdo->prepare("UPDATE websites SET is_active = 1, updated_at = UTC_TIMESTAMP(3) WHERE id IN ({$placeholders})")
                    ->execute($ids);
                $affected = count($websites);
                break;
            case 'deactivate':
                $pdo->prepare("UPDATE websites SET is_active = 0, updated_at = UTC_TIMESTAMP(3) WHERE id IN ({$placeholders})")
                    ->execute($ids);
                $affected = count($websites);
                break;
            case 'delete':
                $pdo->prepare("DELETE FROM websites WHERE id IN ({$placeholders})")->execute($ids);
                $affected = count($websites);
                break;
            case 'check':
                foreach ($websites as $w) {
                    try {
                        MonitorService::runCheckForWebsite($w);
                        $affected++;
                    } catch (Throwable) {
                        // continue
                    }
                }
                break;
        }

        AuditService::write([
            'userId' => $userId,
            'action' => 'website.bulk.' . $input['action'],
            'entityType' => 'Website',
            'metadata' => ['ids' => $ids, 'affected' => $affected],
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        EventBus::publish('website.updated', [
            'action' => 'bulk.' . $input['action'],
            'ids' => $ids,
        ]);

        return ['action' => $input['action'], 'affected' => $affected];
    }

    public static function getUptime(string $id, int $days): array
    {
        if (!self::findById($id)) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }
        $stats = UptimeService::getUptimeStats($id, null, $days);
        $multi = $days === 90 ? UptimeService::getMultiPeriodUptime($id) : null;
        return [
            'websiteId' => $id,
            'days' => $days,
            'stats' => $stats,
            'multi' => $multi,
        ];
    }

    public static function getStats(string $id): array
    {
        $website = self::findById($id);
        if (!$website) {
            throw new AppException('Website not found', 404, 'NOT_FOUND');
        }
        return [
            'website' => self::toApi($website),
            'uptime' => UptimeService::getMultiPeriodUptime($id),
            'responseTimeSeries' => UptimeService::getResponseTimeSeries($id, 7),
        ];
    }

    public static function exportCsv(): string
    {
        $pdo = Database::connection();
        $rows = $pdo->query('SELECT * FROM websites ORDER BY name ASC')->fetchAll();
        $headers = [
            'id', 'name', 'url', 'slug', 'method', 'intervalSeconds', 'timeoutMs',
            'expectedStatus', 'isActive', 'isPublic', 'currentStatus',
            'lastCheckedAt', 'lastResponseMs', 'lastStatusCode',
        ];

        $escape = static function ($v): string {
            $s = $v === null ? '' : (string) $v;
            if (str_contains($s, ',') || str_contains($s, '"') || str_contains($s, "\n")) {
                return '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        };

        $lines = [implode(',', $headers)];
        foreach ($rows as $w) {
            $lines[] = implode(',', array_map($escape, [
                $w['id'],
                $w['name'],
                $w['url'],
                $w['slug'],
                $w['method'],
                $w['interval_seconds'],
                $w['timeout_ms'],
                $w['expected_status'],
                Response::bool($w['is_active']) ? 'true' : 'false',
                Response::bool($w['is_public']) ? 'true' : 'false',
                $w['current_status'],
                $w['last_checked_at'] ? Response::iso($w['last_checked_at']) : '',
                $w['last_response_ms'] ?? '',
                $w['last_status_code'] ?? '',
            ]));
        }
        return implode("\n", $lines);
    }
}
