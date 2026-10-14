<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain
 *
 * @since version 1.5.0
 */
class PlanFormatter
{
    /**
     * @param array $rules
     * @return array
     */
    public function describe(array $rules): array
    {
        $rows = [];

        foreach ($rules as $rule) {
            $coinAmount = (int) ($rule['coinAmount'] ?? 0);
            $currencyCode = (int) ($rule['currency'] ?? 0);
            $rows[] = [
                'from' => (int) ($rule['paymentNumberFrom'] ?? 0),
                'to' => (int) ($rule['paymentNumberTo'] ?? 0),
                'amount' => round($coinAmount / 100, 2),
                'currency' => array_search($currencyCode, Config::CURRENCY_CODE_MAP, true) ?: (string) $currencyCode,
                'intervalValue' => (int) ($rule['intervalValue'] ?? 0),
                'intervalUnit' => strtoupper((string) ($rule['intervalUnit'] ?? '')),
                'isTrial' => $coinAmount === 0,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $rows;
    }
}
