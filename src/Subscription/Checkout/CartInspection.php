<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout;

/**
 * Summary of a cart / order: which lines are subscriptions and how many are regular products.
 *
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout
 *
 * @since version 1.5.0
 */
final class CartInspection
{
    /**
     * @param array $subscriptions
     * @param int $regularCount
     */
    public function __construct(
        public readonly array $subscriptions,
        public readonly int $regularCount
    ) {
    }

    /**
     * @param iterable $lines
     * @return self
     */
    public static function fromLines(iterable $lines): self
    {
        $subscriptions = [];
        $regular = 0;

        foreach ($lines as $line) {
            $line = (array) $line;
            $productId = (int) ($line['virtuemart_product_id'] ?? $line['productId'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? $line['product_quantity'] ?? 1);

            $planId = isset($line['customProductData'])
                ? SubscriptionSelection::extractPlanId($line['customProductData'])
                : SubscriptionSelection::extractPlanIdFromProductAttribute(
                    isset($line['product_attribute']) ? (string) $line['product_attribute'] : null
                );

            if ($planId === null) {
                $regular++;
                continue;
            }

            $subscriptions[] = ['productId' => $productId, 'planId' => $planId, 'quantity' => $quantity];
        }

        return new self($subscriptions, $regular);
    }

    public function hasSubscription(): bool
    {
        return $this->subscriptions !== [];
    }
}
