-- =============================================================
-- MIGRATION: Multi-Platform + Multi-Provider Support
-- =============================================================
-- Run this once against your existing smm_panel database.
-- It is additive/safe: it does not delete your existing
-- services, orders, providers, or api_settings data.
-- =============================================================


-- -------------------------------------------------------------
-- 1. PLATFORMS TABLE
-- -------------------------------------------------------------
-- One row per "New Order" tile: Instagram, TikTok, Facebook,
-- YouTube, Design, RDP, etc. `sort_order` controls the order
-- they appear on the platform picker page.
-- -------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `platforms` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(100) NOT NULL UNIQUE,
    `icon`       VARCHAR(20)  DEFAULT '📦',
    `status`     TINYINT(1)   DEFAULT 1,
    `sort_order` INT          DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `platforms` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Instagram', 'instagram', '📸', 1),
('TikTok',    'tiktok',    '🎵', 2),
('Facebook',  'facebook',  '📘', 3),
('YouTube',   'youtube',   '▶️', 4),
('Twitter/X', 'twitter',   '🐦', 5),
('Telegram',  'telegram',  '✈️', 6),
('WhatsApp',  'whatsapp',  '💬', 7),
('Website & Design', 'website-design', '🖥️', 8),
('Other',     'other',     '📦', 99);


-- -------------------------------------------------------------
-- 2. PROVIDERS TABLE (replaces single-row api_settings)
-- -------------------------------------------------------------
-- Multiple provider APIs can now be saved at once (JAP,
-- ZSMMPanel, SMMCoder, etc). Each service belongs to exactly
-- one provider via services.provider_id.
-- -------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `providers` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `name`             VARCHAR(100) NOT NULL,
    `api_url`          VARCHAR(255) NOT NULL,
    `api_key`          TEXT NOT NULL,
    `usd_to_pkr_rate`  DECIMAL(10,2) NOT NULL DEFAULT 278.00,
    `markup_percent`   DECIMAL(5,2)  NOT NULL DEFAULT 50.00,
    `status`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carry your existing single api_settings row (if any) into the
-- new providers table so nothing breaks after the migration.
INSERT INTO `providers` (`name`, `api_url`, `api_key`, `usd_to_pkr_rate`, `status`)
SELECT `provider_name`, `api_url`, `api_key`, `usd_to_pkr_rate`, `status`
FROM `api_settings`
WHERE NOT EXISTS (SELECT 1 FROM `providers` LIMIT 1);


-- -------------------------------------------------------------
-- 3. SERVICES TABLE — add platform_id + provider_id
-- -------------------------------------------------------------

ALTER TABLE `services`
    ADD COLUMN IF NOT EXISTS `platform_id` INT NULL AFTER `category`,
    ADD COLUMN IF NOT EXISTS `provider_id` INT NULL AFTER `provider_service_id`;

ALTER TABLE `services`
    ADD CONSTRAINT `fk_services_platform`
        FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`)
        ON DELETE SET NULL;

ALTER TABLE `services`
    ADD CONSTRAINT `fk_services_provider`
        FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`)
        ON DELETE SET NULL;

-- Point every existing imported service at the (single, migrated)
-- provider row so old data keeps working immediately.
UPDATE `services`
SET `provider_id` = (SELECT id FROM `providers` ORDER BY id ASC LIMIT 1)
WHERE `provider_id` IS NULL
  AND `provider_service_id` IS NOT NULL;

-- Best-effort auto-map existing services to a platform by
-- matching their category/name text. Anything that doesn't
-- match falls under "Other" — reassign manually afterwards
-- from admin/services.php.
UPDATE `services` s
JOIN `platforms` p ON (s.category LIKE CONCAT('%', p.name, '%') OR s.name LIKE CONCAT('%', p.name, '%'))
SET s.platform_id = p.id
WHERE s.platform_id IS NULL;

UPDATE `services`
SET `platform_id` = (SELECT id FROM `platforms` WHERE slug = 'other' LIMIT 1)
WHERE `platform_id` IS NULL;
