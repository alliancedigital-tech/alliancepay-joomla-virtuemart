<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * A VirtueMart order item as paid: the line total with tax after product-level discounts, in the
 * order currency, in kopecks.
 *
 * @since version 1.6.0
 */
final class OrderItemLine
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly int $quantity,
        public readonly int $subtotalKop
    ) {
    }
}
