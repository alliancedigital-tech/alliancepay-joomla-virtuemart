<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;

/**
 * Class AmountVerifier.
 */
class AmountVerifier
{
    /**
     * @param Subscription $subscription
     * @param int|null $paymentNumber
     * @param int|null $chargedCoinAmount
     * @param array|null $currentPlanRules
     * @return string|null
     */
    public function check(
        Subscription $subscription,
        ?int $paymentNumber,
        ?int $chargedCoinAmount,
        ?array $currentPlanRules = null
    ): ?string {
        if ($paymentNumber === null || $chargedCoinAmount === null) {
            return null;
        }

        $expected = $subscription->getExpectedCoinAmount($paymentNumber);

        if ($expected === null || $expected === $chargedCoinAmount) {
            return null;
        }

        if ($currentPlanRules !== null) {
            foreach ($currentPlanRules as $rule) {
                if ($paymentNumber >= (int) ($rule['paymentNumberFrom'] ?? 0)
                    && $paymentNumber <= (int) ($rule['paymentNumberTo'] ?? 0)
                    && (int) ($rule['coinAmount'] ?? -1) === $chargedCoinAmount
                ) {
                    return null;
                }
            }
        }

        return sprintf('Payment #%d: expected %d, charged %d (minor units)', $paymentNumber, $expected, $chargedCoinAmount);
    }
}
