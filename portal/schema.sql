-- Portal links tables (uses existing webmonitor database)
USE webmonitor;

CREATE TABLE IF NOT EXISTS portal_profile (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  username VARCHAR(100) NOT NULL DEFAULT 'Shaun',
  bio VARCHAR(500) NOT NULL DEFAULT 'Here Is My Project ✨',
  avatar VARCHAR(255) NOT NULL DEFAULT 'assets/img/pfp.png',
  admin_password_hash VARCHAR(255) NOT NULL,
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_links (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
