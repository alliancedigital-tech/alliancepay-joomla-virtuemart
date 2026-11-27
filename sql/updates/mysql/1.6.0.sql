ALTER TABLE `#__virtuemart_payment_plg_alliance_refund`
    ADD COLUMN IF NOT EXISTS `items` LONGTEXT NULL;

CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_alliance_fiscalization` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `order_id` INT NOT NULL,
    `operation_id` VARCHAR(255) NOT NULL,
    `operation_type` VARCHAR(16) NOT NULL,
    `merchant_request_id` VARCHAR(64) NOT NULL,
    `bucket_id` VARCHAR(64) NULL DEFAULT NULL,
    `status` VARCHAR(16) NOT NULL,
    `receipt_comment` VARCHAR(255) NULL DEFAULT NULL,
    `fiscal_number` VARCHAR(64) NULL DEFAULT NULL,
    `receipt_url` TEXT NULL,
    `fiscalization_date_time` DATETIME NULL DEFAULT NULL,
    `fiscalization_modification_date_time` DATETIME NULL DEFAULT NULL,
    `modification_date_time` DATETIME NULL DEFAULT NULL,
    `error_code` VARCHAR(32) NULL DEFAULT NULL,
    `error_description` TEXT NULL,
    `request_payload` LONGTEXT NOT NULL,
    `response_payload` LONGTEXT NULL,
    `callback_payload` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_alliance_fisc_operation` (`operation_id`),
    UNIQUE INDEX `idx_alliance_fisc_merchant_req` (`merchant_request_id`),
    INDEX `idx_alliance_fisc_order` (`order_id`),
    INDEX `idx_alliance_fisc_bucket` (`bucket_id`),
    INDEX `idx_alliance_fisc_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
