-- WebMonitor MySQL schema (InnoDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS webmonitor
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE webmonitor;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS monitor_logs;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS websites;
DROP TABLE IF EXISTS telegram_settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id VARCHAR(30) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','USER') NOT NULL DEFAULT 'ADMIN',
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE websites (
  id VARCHAR(30) NOT NULL,
  name VARCHAR(200) NOT NULL,
  url VARCHAR(2048) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  method ENUM('GET','HEAD','POST') NOT NULL DEFAULT 'GET',
  interval_seconds INT NOT NULL DEFAULT 60,
  timeout_ms INT NOT NULL DEFAULT 10000,
  expected_status INT NOT NULL DEFAULT 200,
  keyword VARCHAR(500) NULL,
  headers_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  current_status ENUM('UP','DOWN','UNKNOWN','DEGRADED') NOT NULL DEFAULT 'UNKNOWN',
  last_checked_at DATETIME(3) NULL,
  last_response_ms INT NULL,
  last_status_code INT NULL,
  last_error TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY websites_slug_unique (slug),
  KEY websites_active_checked_idx (is_active, last_checked_at),
  KEY websites_status_idx (current_status),
  KEY websites_name_idx (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE monitor_logs (
  id VARCHAR(30) NOT NULL,
  website_id VARCHAR(30) NOT NULL,
  status ENUM('UP','DOWN','UNKNOWN','DEGRADED') NOT NULL,
  status_code INT NULL,
  response_ms INT NULL,
  error_message TEXT NULL,
  checked_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY monitor_logs_website_checked_idx (website_id, checked_at),
  KEY monitor_logs_checked_idx (checked_at),
  KEY monitor_logs_status_idx (status),
  CONSTRAINT monitor_logs_website_fk
    FOREIGN KEY (website_id) REFERENCES websites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incidents (
  id VARCHAR(30) NOT NULL,
  website_id VARCHAR(30) NOT NULL,
  status ENUM('UP','DOWN','UNKNOWN','DEGRADED') NOT NULL,
  started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  resolved_at DATETIME(3) NULL,
  summary TEXT NULL,
  PRIMARY KEY (id),
  KEY incidents_website_started_idx (website_id, started_at),
  CONSTRAINT incidents_website_fk
    FOREIGN KEY (website_id) REFERENCES websites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE telegram_settings (
  id VARCHAR(30) NOT NULL,
  bot_token VARCHAR(512) NOT NULL DEFAULT '',
  chat_id VARCHAR(128) NOT NULL DEFAULT '',
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  notify_on_down TINYINT(1) NOT NULL DEFAULT 1,
  notify_on_up TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id VARCHAR(30) NOT NULL,
  user_id VARCHAR(30) NULL,
  action VARCHAR(128) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id VARCHAR(64) NULL,
  metadata TEXT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY audit_logs_created_idx (created_at),
  KEY audit_logs_user_idx (user_id),
  KEY audit_logs_entity_idx (entity_type, entity_id),
  CONSTRAINT audit_logs_user_fk
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Portal (My Links) tables
CREATE TABLE IF NOT EXISTS portal_profile (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  username VARCHAR(100) NOT NULL DEFAULT 'Shaun',
  bio VARCHAR(500) NOT NULL DEFAULT 'Here Is My Project',
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
