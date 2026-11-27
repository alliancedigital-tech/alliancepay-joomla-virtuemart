<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * An order line (item, shipment or payment fee) with its share of the operation amount, in UAH kopecks.
 *
 * @since version 1.6.0
 */
final class AllocatedLine
{
    public const KIND_ITEM = 'item';
    public const KIND_SHIPMENT = 'shipment';
    public const KIND_PAYMENT = 'payment';

    public function __construct(
        public readonly string $kind,
        public readonly int $orderItemId,
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly int $quantity,
        public readonly int $totalKop
    ) {
    }
}
