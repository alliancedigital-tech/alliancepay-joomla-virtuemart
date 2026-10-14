<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout
 *
 * @since version 1.5.0
 */
final class SubscriptionSelection
{
    public const MODE_ONCE = 'once';
    public const MODE_SUBSCRIPTION = 'subscription';

    public const FIELD_MODE = 'mode';
    public const FIELD_PLAN_ID = 'plan_id';

    /**
     * @param mixed $customProductData
     * @return int|null
     */
    public static function extractPlanId(mixed $customProductData): ?int
    {
        if (!is_array($customProductData)) {
            return null;
        }

        foreach ($customProductData as $byCustomField) {
            if (!is_array($byCustomField)) {
                continue;
            }

            foreach ($byCustomField as $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (($value[self::FIELD_MODE] ?? null) === self::MODE_SUBSCRIPTION) {
                    $planId = (int) ($value[self::FIELD_PLAN_ID] ?? 0);

                    return $planId > 0 ? $planId : null;
                }
            }
        }

        return null;
    }

    /**
     * @param string|null $productAttribute
     * @return int|null
     */
    public static function extractPlanIdFromProductAttribute(?string $productAttribute): ?int
    {
        if ($productAttribute === null || $productAttribute === '') {
            return null;
        }

        $decoded = json_decode($productAttribute, true);

        return self::extractPlanId($decoded);
    }
}
