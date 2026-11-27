<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * The amounts of a VirtueMart order, all in the order currency, in kopecks.
 *
 * @since version 1.6.0
 */
final class OrderSnapshot
{
    /**
     * @param OrderItemLine[] $items
     * @param int $shipmentKop shipment cost including tax
     * @param int $paymentKop payment fee including tax (negative = a discount)
     * @param int $orderTotalKop what the customer pays: items + shipment + payment - order-level discounts
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly array $items,
        public readonly int $shipmentKop,
        public readonly int $paymentKop,
        public readonly int $orderTotalKop
    ) {
    }
}
