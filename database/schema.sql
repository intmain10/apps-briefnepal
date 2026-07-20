-- =========================================================================
-- OmniTools — MySQL schema + seed data
-- Import via phpMyAdmin or:  mysql -u USER -p DBNAME < database/schema.sql
--
-- The platform runs WITHOUT a database (the tool registry lives in PHP),
-- but a DB unlocks the admin panel, analytics, feedback storage and CMS blog.
-- =========================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- Optional: create + select the database (uncomment / edit as needed)
-- CREATE DATABASE IF NOT EXISTS `omnitools` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `omnitools`;

-- -------------------------------------------------------------------------
-- users (admins)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','editor') NOT NULL DEFAULT 'admin',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- categories
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(60)  NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `icon`        VARCHAR(40)  NULL,
  `color`       VARCHAR(20)  NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- tools (mirror of the code registry; lets admins toggle/feature tools)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(80)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `description` VARCHAR(255) NULL,
  `keywords`    VARCHAR(255) NULL,
  `is_popular`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_trending` TINYINT(1) NOT NULL DEFAULT 0,
  `is_new`      TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `views`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- blogs (CMS articles; overrides/extends the static blog store)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blogs` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(160) NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `excerpt`      VARCHAR(320) NULL,
  `body`         MEDIUMTEXT NULL,
  `category`     VARCHAR(60) NULL,
  `author`       VARCHAR(100) NULL,
  `related_tool` VARCHAR(80) NULL,
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'published',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- favorites (per-user saved tools — optional accounts)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favorites` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `tool_slug`  VARCHAR(80) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- search_logs (anonymous analytics)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `search_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `query`      VARCHAR(190) NOT NULL,
  `ip_hash`    VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_query` (`query`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- feedback (contact form submissions)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `type`       VARCHAR(40) NOT NULL DEFAULT 'Feedback',
  `message`    TEXT NOT NULL,
  `ip_hash`    VARCHAR(64) NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- settings (key/value site configuration)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- =========================================================================
-- SEED DATA
-- =========================================================================

-- Default admin — email: admin@omnitools.local  password: ChangeMe123!
-- (Change this immediately after your first login.)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
  ('Administrator', 'admin@omnitools.local', '$2y$12$SJfy/nD6bq2kIHGalr43xuTIYTv3SPQcmD3c9mZlyqGioT33W8vuy', 'admin')
ON DUPLICATE KEY UPDATE `email` = `email`;

-- Categories
INSERT INTO `categories` (`slug`, `name`, `description`, `icon`, `color`, `sort_order`) VALUES
  ('pdf','PDF','Merge, split, convert and optimise PDF documents.','file','#ef4444',1),
  ('image','Image','Compress, resize, crop and convert images.','image','#f59e0b',2),
  ('video','Video','Inspect and work with video files.','video','#8b5cf6',3),
  ('audio','Audio','Cut, convert, normalise and boost audio.','audio','#ec4899',4),
  ('text','Text','Count, sort, clean and transform text.','text','#10b981',5),
  ('developer','Developer','Formatters, encoders and validators.','code','#3b82f6',6),
  ('seo','SEO','Meta tags, schema, sitemaps and more.','search','#06b6d4',7),
  ('finance','Finance','Loan, EMI, tax and investment calculators.','money','#22c55e',8),
  ('utilities','Utilities','QR codes, passwords, colors and more.','grid','#6366f1',9),
  ('calculators','Calculators','Fast, accurate everyday calculators.','calc','#14b8a6',10),
  ('documents','Documents','Convert CSV, JSON, Markdown and more.','doc','#0ea5e9',11),
  ('ai','AI','On-device text intelligence.','sparkles','#a855f7',12),
  ('converters','Converters','Convert units of every kind.','swap','#f43f5e',13)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('site_name','OmniTools'),
  ('site_tagline','Everything You Need. One Platform.'),
  ('adsense_enabled','0'),
  ('adsense_client',''),
  ('maintenance_mode','0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Sample CMS blog post (the code store provides more out of the box)
INSERT INTO `blogs` (`slug`,`title`,`excerpt`,`body`,`category`,`author`,`related_tool`,`status`) VALUES
  ('welcome-to-omnitools','Welcome to OmniTools','The story behind the fastest, cleanest free-tools platform on the web.',
   '## Hello, world\n\nOmniTools launched with 100+ free tools and a simple promise: **fast, private and beautiful**.\n\nThis post is served from the MySQL `blogs` table — proof the CMS is wired up. Edit or delete it from the admin panel.',
   'Company','OmniTools Team','','published')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
