<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

use InvalidArgumentException;
use OverflowException;

/**
 * Splits an integer total (kopecks) between weights proportionally. Every share is rounded down and the
 * rounding remainder goes to the last position that has a positive weight, so the shares always add up
 * to the total exactly. Pure PHP, integer arithmetic only.
 *
 * @since version 1.6.0
 */
final class ProportionalAllocator
{
    private function __construct()
    {
    }

    /**
     * @param array<int|string, int> $weights non-negative integers, at least one positive
     * @param int $total non-negative
     *
     * @return array<int|string, int> shares under the same keys
     */
    public static function allocate(array $weights, int $total): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException('Total must not be negative.');
        }

        $sum = 0;
        $lastPositiveKey = null;

        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Weights must not be negative.');
            }

            $sum += $weight;

            if ($weight > 0) {
                $lastPositiveKey = $key;
            }
        }

        if ($sum <= 0 || $lastPositiveKey === null) {
            throw new InvalidArgumentException('At least one weight must be positive.');
        }

        if ($total > intdiv(PHP_INT_MAX, max(1, max($weights)))) {
            throw new OverflowException('Amount is too large to allocate.');
        }

        $shares = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $shares[$key] = intdiv($total * $weight, $sum);
            $allocated += $shares[$key];
        }

        $shares[$lastPositiveKey] += $total - $allocated;

        return $shares;
    }
}
