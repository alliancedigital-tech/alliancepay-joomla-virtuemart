ALTER TABLE `#__virtuemart_payment_plg_alliance_order`
    ADD COLUMN IF NOT EXISTS `original_authorized_amount` INT NULL DEFAULT NULL;
