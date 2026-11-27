<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * One products[] entry before the fiscal attributes are attached.
 *
 * @since version 1.6.0
 */
final class ProductLine
{
    public function __construct(
        public readonly string $id,
        public readonly int $productId,
        public readonly string $name,
        public readonly int $count,
        public readonly int $unitKop
    ) {
    }

    public function totalKop(): int
    {
        return $this->count * $this->unitKop;
    }
}
