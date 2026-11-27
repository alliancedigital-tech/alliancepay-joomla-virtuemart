<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * A successful PURCHASE or REFUND operation of an order that can be fiscalized.
 *
 * @since version 1.6.0
 */
final class FiscalOperation
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $operationId,
        public readonly FiscalOperationType $type,
        public readonly int $amountKop,
        public readonly ?string $originalOperationId = null,
        public readonly ?RefundSelection $refundSelection = null
    ) {
    }
}
