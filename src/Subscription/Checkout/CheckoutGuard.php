<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout
 *
 * @since version 1.5.0
 */
class CheckoutGuard
{
    public const VIOLATION_DISABLED = 'disabled';
    public const VIOLATION_GUEST = 'guest';
    public const VIOLATION_MULTIPLE_SUBSCRIPTIONS = 'multiple_subscriptions';
    public const VIOLATION_MIXED_CART = 'mixed_cart';
    public const VIOLATION_QUANTITY = 'quantity';
    public const VIOLATION_PLAN_UNAVAILABLE = 'plan_unavailable';
    public const VIOLATION_PLAN_MISMATCH = 'plan_mismatch';

    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly bool $subscriptionsEnabled
    ) {
    }

    /**
     * @param CartInspection $inspection
     * @param int $userId
     * @return string|null
     */
    public function check(CartInspection $inspection, int $userId): ?string
    {
        if (!$inspection->hasSubscription()) {
            return null;
        }

        if (!$this->subscriptionsEnabled) {
            return self::VIOLATION_DISABLED;
        }

        if ($userId < 1) {
            return self::VIOLATION_GUEST;
        }

        if (count($inspection->subscriptions) > 1) {
            return self::VIOLATION_MULTIPLE_SUBSCRIPTIONS;
        }

        if ($inspection->regularCount > 0) {
            return self::VIOLATION_MIXED_CART;
        }

        $line = $inspection->subscriptions[0];

        if ($line['quantity'] !== 1) {
            return self::VIOLATION_QUANTITY;
        }

        $plan = $this->planRepository->find($line['planId']);

        if ($plan === null || !$plan->isOfferable()) {
            return self::VIOLATION_PLAN_UNAVAILABLE;
        }

        if (!$this->planRepository->isProductInPlanChain($plan->productId, $line['productId'])) {
            return self::VIOLATION_PLAN_MISMATCH;
        }

        return null;
    }

    /**
     * Language key for a violation.
     */
    public static function messageKey(string $violation): string
    {
        return 'VMPAYMENT_ALLIANCEPAY_SUB_ERR_' . strtoupper($violation);
    }
}
