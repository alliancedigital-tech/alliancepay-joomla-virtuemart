<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * One reason why a fiscalization request cannot be built or sent: a language key plus sprintf arguments.
 * The domain does not translate; the caller turns issues into text with Text::sprintf().
 *
 * @since version 1.6.0
 */
final class FiscalizationIssue
{
    public const NO_NOTIFICATION_URL = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_NO_NOTIFICATION_URL';
    public const NO_PRODUCTS = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_NO_PRODUCTS';
    public const MISSING_FIELD = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_MISSING_FIELD';
    public const ORDER_NOT_FOUND = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_ORDER_NOT_FOUND';
    public const OPERATION_NOT_FOUND = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_OPERATION_NOT_FOUND';
    public const ORIGINAL_NOT_FOUND = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_ORIGINAL_NOT_FOUND';
    public const AMOUNT_ZERO = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_AMOUNT_ZERO';
    public const PRICE_NOT_POSITIVE = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_PRICE_NOT_POSITIVE';
    public const SUM_MISMATCH = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_SUM_MISMATCH';
    public const REFUND_ITEMS_MISSING = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_REFUND_ITEMS_MISSING';
    public const REFUND_ITEM_UNKNOWN = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_REFUND_ITEM_UNKNOWN';
    public const REFUND_QUANTITY = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_REFUND_QUANTITY';
    public const CLASSIFICATION_REQUIRED_FOR_PRODUCT = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_PRODUCT_CLASSIFICATION';
    public const API_MISSING_FIELD = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_MISSING_FIELD';
    public const API_INVALID_FORMAT = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_INVALID_FORMAT';
    public const API_PAYMENT_NOT_FOUND = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_PAYMENT_NOT_FOUND';
    public const API_ORIGINAL_RECEIPT_NOT_FOUND = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_ORIGINAL_RECEIPT_NOT_FOUND';
    public const API_ERROR = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_ERROR';
    public const API_UNAVAILABLE = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_UNAVAILABLE';
    public const NOT_ALLOWED = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_NOT_ALLOWED';

    /**
     * @param array<int, string|int|float> $params
     */
    public function __construct(
        public readonly string $key,
        public readonly array $params = []
    ) {
    }
}
