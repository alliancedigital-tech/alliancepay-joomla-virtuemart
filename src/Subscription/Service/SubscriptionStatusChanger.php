<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SubscriptionStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class SubscriptionStatusChanger
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PlanRepository $planRepository
    ) {
    }

    public function change(Subscription $subscription, SubscriptionStatus $new): void
    {
        if ($subscription->status === $new) {
            return;
        }

        $this->subscriptionRepository->update($subscription->id, ['status' => $new->value]);

        if ($subscription->status->countsAsActive() !== $new->countsAsActive()) {
            $this->planRepository->adjustActiveCount($subscription->planId, $new->countsAsActive() ? 1 : -1);
        }
    }
}
