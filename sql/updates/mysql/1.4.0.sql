ALTER TABLE `#__virtuemart_payment_plg_alliance_order`
    ADD COLUMN IF NOT EXISTS `currency_code` INT(3) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `conversion_rate` DECIMAL(15,6) NULL DEFAULT NULL;
