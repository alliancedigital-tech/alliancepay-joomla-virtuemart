DROP TABLE IF EXISTS `#__virtuemart_payment_plg_alliance_auth_data`;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_pubkey_cache` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `kid` VARCHAR(255) NOT NULL,
    `jwk` LONGTEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_pubkey_kid` (`kid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_plan_template` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category_indicator` VARCHAR(100) NOT NULL DEFAULT '',
    `rules_template` LONGTEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_tpl_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_subscription_plan` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `provider_plan_id` VARCHAR(255) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `template_id` INT UNSIGNED NULL DEFAULT NULL,
    `plan_key` CHAR(64) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `active_subscriptions_count` INT NOT NULL DEFAULT 0,
    `sync_status` VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    `sync_error` TEXT NULL,
    `archive_candidate` TINYINT(1) NOT NULL DEFAULT 0,
    `version_id` VARCHAR(255) NULL DEFAULT NULL,
    `merchant_id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `category_indicator` VARCHAR(100) NOT NULL DEFAULT '',
    `rules` LONGTEXT NOT NULL,
    `applies_from` DATETIME NULL DEFAULT NULL,
    `applies_to` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_plan_provider_id` (`provider_plan_id`),
    UNIQUE INDEX `idx_alliance_plan_key` (`plan_key`),
    INDEX `idx_alliance_plan_product` (`product_id`),
    INDEX `idx_alliance_plan_template` (`template_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_subscription` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `virtuemart_order_id` INT UNSIGNED NOT NULL,
    `virtuemart_user_id` INT UNSIGNED NOT NULL,
    `plan_id` INT UNSIGNED NOT NULL,
    `rules_snapshot` LONGTEXT NOT NULL,
    `provider_order_id` VARCHAR(255) NULL DEFAULT NULL,
    `merchant_request_id` VARCHAR(255) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `callback_data` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_sub_provider_order` (`provider_order_id`),
    INDEX `idx_alliance_sub_order` (`virtuemart_order_id`),
    INDEX `idx_alliance_sub_user` (`virtuemart_user_id`),
    INDEX `idx_alliance_sub_plan` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_scheduled_payment` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `subscription_id` INT UNSIGNED NOT NULL,
    `provider_order_id` VARCHAR(255) NOT NULL,
    `scheduled_payment_id` VARCHAR(255) NULL DEFAULT NULL,
    `payment_number` INT UNSIGNED NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'WAITING',
    `scheduled_date` DATETIME NULL DEFAULT NULL,
    `executed_at` DATETIME NULL DEFAULT NULL,
    `operation_id` VARCHAR(255) NULL DEFAULT NULL,
    `coin_amount` INT NULL DEFAULT NULL,
    `retry_count` INT NOT NULL DEFAULT 0,
    `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
    `review_note` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_sp_scheduled` (`provider_order_id`, `scheduled_payment_id`),
    UNIQUE INDEX `idx_alliance_sp_operation` (`operation_id`),
    INDEX `idx_alliance_sp_subscription` (`subscription_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_plan_version_history` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `plan_id` INT UNSIGNED NOT NULL,
    `old_version_id` VARCHAR(255) NULL DEFAULT NULL,
    `new_version_id` VARCHAR(255) NULL DEFAULT NULL,
    `old_rules` LONGTEXT NOT NULL,
    `new_rules` LONGTEXT NOT NULL,
    `applies_from` DATETIME NULL DEFAULT NULL,
    `reason` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_alliance_pvh_plan` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_subscription_audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `plan_id` INT UNSIGNED NULL DEFAULT NULL,
    `product_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `details` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_alliance_audit_plan` (`plan_id`),
    INDEX `idx_alliance_audit_action` (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

