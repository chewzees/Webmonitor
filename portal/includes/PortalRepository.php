<?php
declare(strict_types=1);

final class PortalRepository
{
    public static function pdo(): PDO
    {
        return Database::connection();
    }

    public static function ensureInstalled(): void
    {
        $pdo = self::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS portal_profile (
              id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
              username VARCHAR(100) NOT NULL DEFAULT 'Shaun',
              bio VARCHAR(500) NOT NULL DEFAULT 'Here Is My Project ✨',
              avatar VARCHAR(255) NOT NULL DEFAULT 'assets/img/pfp.png',
              admin_password_hash VARCHAR(255) NOT NULL,
              updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS portal_links (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              title VARCHAR(200) NOT NULL,
              description VARCHAR(500) NOT NULL DEFAULT '',
              url VARCHAR(2048) NOT NULL,
              icon VARCHAR(120) NOT NULL DEFAULT 'fas fa-link',
              sort_order INT NOT NULL DEFAULT 0,
              is_custom TINYINT(1) NOT NULL DEFAULT 0,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
              PRIMARY KEY (id),
              KEY portal_links_active_sort_idx (is_active, sort_order),
              KEY portal_links_created_idx (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM portal_profile')->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('Portal123!', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO portal_profile (id, username, bio, avatar, admin_password_hash)
                 VALUES (1, :username, :bio, :avatar, :hash)'
            );
            $stmt->execute([
                'username' => 'Shaun',
                'bio' => 'Here Is My Project ✨',
                'avatar' => 'assets/img/pfp.png',
                'hash' => $hash,
            ]);
        }

        $linkCount = (int) $pdo->query('SELECT COUNT(*) FROM portal_links')->fetchColumn();
        if ($linkCount === 0) {
            self::seedDefaultLinks();
        }
    }

    /** @return array{username:string,bio:string,avatar:string} */
    public static function getProfile(): array
    {
        $row = self::pdo()->query(
            'SELECT username, bio, avatar FROM portal_profile WHERE id = 1 LIMIT 1'
        )->fetch();

        if (!$row) {
            return [
                'username' => 'Shaun',
                'bio' => 'Here Is My Project ✨',
                'avatar' => 'assets/img/pfp.png',
            ];
        }

        return [
            'username' => (string) $row['username'],
            'bio' => (string) $row['bio'],
            'avatar' => (string) $row['avatar'],
        ];
    }

    public static function verifyAdminPassword(string $password): bool
    {
        $hash = self::pdo()->query(
            'SELECT admin_password_hash FROM portal_profile WHERE id = 1 LIMIT 1'
        )->fetchColumn();

        return is_string($hash) && password_verify($password, $hash);
    }

    public static function updateProfile(string $username, string $bio, string $avatar): void
    {
        $stmt = self::pdo()->prepare(
            'UPDATE portal_profile
             SET username = :username, bio = :bio, avatar = :avatar
             WHERE id = 1'
        );
        $stmt->execute([
            'username' => $username,
            'bio' => $bio,
            'avatar' => $avatar,
        ]);
    }

    public static function updateAdminPassword(string $password): void
    {
        $stmt = self::pdo()->prepare(
            'UPDATE portal_profile SET admin_password_hash = :hash WHERE id = 1'
        );
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    /**
     * @return list<array{
     *   id:int,
     *   title:string,
     *   description:string,
     *   url:string,
     *   icon:string,
     *   isCustom:bool,
     *   createdAt:int,
     *   sortOrder:int
     * }>
     */
    public static function getActiveLinks(): array
    {
        $stmt = self::pdo()->query(
            'SELECT id, title, description, url, icon, is_custom, sort_order, created_at
             FROM portal_links
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );

        $links = [];
        while ($row = $stmt->fetch()) {
            $links[] = self::mapLink($row);
        }
        return $links;
    }

    /**
     * @return list<array{
     *   id:int,
     *   title:string,
     *   description:string,
     *   url:string,
     *   icon:string,
     *   isCustom:bool,
     *   createdAt:int,
     *   sortOrder:int
     * }>
     */
    public static function getAllLinks(): array
    {
        $stmt = self::pdo()->query(
            'SELECT id, title, description, url, icon, is_custom, sort_order, created_at, is_active
             FROM portal_links
             ORDER BY sort_order ASC, id ASC'
        );

        $links = [];
        while ($row = $stmt->fetch()) {
            $mapped = self::mapLink($row);
            $mapped['isActive'] = (bool) $row['is_active'];
            $links[] = $mapped;
        }
        return $links;
    }

    public static function addLink(
        string $title,
        string $description,
        string $url,
        string $icon,
        bool $isCustom = true
    ): int {
        $pdo = self::pdo();
        $nextOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM portal_links')->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO portal_links (title, description, url, icon, sort_order, is_custom, is_active)
             VALUES (:title, :description, :url, :icon, :sort_order, :is_custom, 1)'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon !== '' ? $icon : 'fas fa-link',
            'sort_order' => $nextOrder,
            'is_custom' => $isCustom ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateLink(
        int $id,
        string $title,
        string $description,
        string $url,
        string $icon,
        int $sortOrder,
        bool $isActive
    ): void {
        $stmt = self::pdo()->prepare(
            'UPDATE portal_links
             SET title = :title,
                 description = :description,
                 url = :url,
                 icon = :icon,
                 sort_order = :sort_order,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon !== '' ? $icon : 'fas fa-link',
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public static function deleteLink(int $id): void
    {
        $stmt = self::pdo()->prepare('DELETE FROM portal_links WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function findLink(int $id): ?array
    {
        $stmt = self::pdo()->prepare(
            'SELECT id, title, description, url, icon, is_custom, sort_order, created_at, is_active
             FROM portal_links WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $mapped = self::mapLink($row);
        $mapped['isActive'] = (bool) $row['is_active'];
        return $mapped;
    }

    /** @param array<string, mixed> $row */
    private static function mapLink(array $row): array
    {
        $createdAt = strtotime((string) $row['created_at']);
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'url' => (string) $row['url'],
            'icon' => (string) $row['icon'],
            'isCustom' => (bool) $row['is_custom'],
            'createdAt' => $createdAt !== false ? $createdAt * 1000 : 0,
            'sortOrder' => (int) $row['sort_order'],
        ];
    }

    private static function seedDefaultLinks(): void
    {
        $defaults = [
            ['Inbound Shipment System', 'Track and manage shipments', 'https://chongchewzee.kolejsynergy.com/shipment/login.php', 'fas fa-ship'],
            ['Attendance System', 'Manage attendance records', 'https://chongchewzee.kolejsynergy.com/attendance3/login.php?redirect=%2Fattendance3%2Findex.php', 'fas fa-clipboard-check'],
            ['Warehousing System', 'Manage inventory and warehouse operations', 'https://chongchewzee.kolejsynergy.com/warehousing/', 'fas fa-warehouse'],
            ['Licences System', 'Manage licences and permissions', 'https://chongchewzee.kolejsynergy.com/licences/login.html', 'fas fa-id-card'],
            ['Website Management System', 'Manage website content', 'https://master-prompt-admin.replit.app', 'fas fa-cogs'],
            ['Delivery Tracker System', 'Track deliveries in real time', 'https://parcel-flow.replit.app', 'fas fa-shipping-fast'],
            ['E-Commerce', 'Online storefront', 'https://chongchewzee.kolejsynergy.com/ecom/public/index.php', 'fas fa-store'],
            ['Cooperative System', 'Cooperative management system', 'https://chongchewzee.kolejsynergy.com/cooperative%20system/index.php?page=login', 'fas fa-building-columns'],
            ['Telegram Bot Reminder System', 'Telegram reminders', 'https://telegram-reminder-system.replit.app/login', 'fab fa-telegram'],
            ['Location Tracker System', 'Location tracking', 'https://life-map-chewzees1408.replit.app/login', 'fas fa-map-marker-alt'],
            ['POS System', 'Cashier / point of sale', 'https://chongchewzee.kolejsynergy.com/possss/auth/login.php', 'fas fa-cash-register'],
            ['Background Remover', 'Remove backgrounds with ease', 'https://chongchewzee.kolejsynergy.com/BG_remover/', 'fas fa-cut'],
            ['Online Resume', 'My online resume', 'https://chongchewzee.kolejsynergy.com/resume/login.php', 'fas fa-graduation-cap'],
            ['e-RPH System', 'Digital lesson plan management', 'https://chongchewzee.kolejsynergy.com/e-RPH/index.php?logged_out=1', 'fas fa-chalkboard-teacher'],
            ['OCR System', 'Convert scans into editable text', 'https://chongchewzee.kolejsynergy.com/OCR/manual', 'fas fa-file-alt'],
            ['Video Editing', 'Edit video with ease', 'https://chongchewzee.kolejsynergy.com/vidih/', 'fas fa-video'],
        ];

        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO portal_links (title, description, url, icon, sort_order, is_custom, is_active)
             VALUES (:title, :description, :url, :icon, :sort_order, 0, 1)'
        );

        foreach ($defaults as $index => [$title, $description, $url, $icon]) {
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'url' => $url,
                'icon' => $icon,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
