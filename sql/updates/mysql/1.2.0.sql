ALTER TABLE `#__virtuemart_payment_plg_alliance_order`
    ADD COLUMN IF NOT EXISTS `transaction_type` SMALLINT NULL DEFAULT NULL;
