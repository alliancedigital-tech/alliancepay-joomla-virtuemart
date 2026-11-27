<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Amounts: the plugin stores money in kopecks (integers), the fiscalization API takes hryvnias.
 *
 * @since version 1.6.0
 */
final class MoneyConverter
{
    private function __construct()
    {
    }

    /**
     * @param string|int|float|null $amount an amount in currency units (e.g. a DECIMAL column value)
     */
    public static function toKopecks(string|int|float|null $amount): int
    {
        return (int) round(round((float) $amount, 6) * 100);
    }

    public static function kopecksToUah(int $kopecks): float
    {
        return round($kopecks / 100, 2);
    }
}
