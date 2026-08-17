<?php
declare(strict_types=1);

final class AuditService
{
    /** @param array<string, mixed> $params */
    public static function write(array $params): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (id, user_id, action, entity_type, entity_id, metadata, ip, user_agent, created_at)
             VALUES (:id, :user_id, :action, :entity_type, :entity_id, :metadata, :ip, :user_agent, UTC_TIMESTAMP(3))'
        );
        $stmt->execute([
            'id' => Cuid::generate(),
            'user_id' => $params['userId'] ?? null,
            'action' => $params['action'],
            'entity_type' => $params['entityType'] ?? null,
            'entity_id' => $params['entityId'] ?? null,
            'metadata' => isset($params['metadata'])
                ? (is_string($params['metadata']) ? $params['metadata'] : json_encode($params['metadata']))
                : null,
            'ip' => $params['ip'] ?? null,
            'user_agent' => $params['userAgent'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $query */
    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($query['action'])) {
            $where[] = 'a.action LIKE :action';
            $params['action'] = '%' . $query['action'] . '%';
        }
        if (!empty($query['userId'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = $query['userId'];
        }
        if (!empty($query['from'])) {
            $where[] = 'a.created_at >= :from';
            $params['from'] = self::toMysqlDatetime((string) $query['from']);
        }
        if (!empty($query['to'])) {
            $where[] = 'a.created_at <= :to';
            $params['to'] = self::toMysqlDatetime((string) $query['to']);
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $pdo = Database::connection();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT a.*, u.id AS u_id, u.email AS u_email, u.name AS u_name
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = array_map(static function (array $r): array {
            return [
                'id' => $r['id'],
                'userId' => $r['user_id'],
                'action' => $r['action'],
                'entityType' => $r['entity_type'],
                'entityId' => $r['entity_id'],
                'metadata' => $r['metadata'],
                'ip' => $r['ip'],
                'userAgent' => $r['user_agent'],
                'createdAt' => Response::iso($r['created_at']),
                'user' => $r['u_id']
                    ? ['id' => $r['u_id'], 'email' => $r['u_email'], 'name' => $r['u_name']]
                    : null,
            ];
        }, $rows);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max($limit, 1)),
        ];
    }

    private static function toMysqlDatetime(string $iso): string
    {
        $dt = new DateTimeImmutable($iso);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
