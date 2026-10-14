<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class SubscriptionDiagnosticsService
{
    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PlanRepository $planRepository,
        private readonly ScheduledPaymentRepository $paymentRepository
    ) {
    }

    /**
     * @param int $subscriptionId
     * @return array
     */
    public function load(int $subscriptionId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if ($subscription === null) {
            return ['subscription' => null, 'plan' => null, 'payments' => [], 'operations' => [], 'warning' => null];
        }

        $operations = [];
        $warning = null;

        if ($subscription->providerOrderId !== null) {
            try {
                $operations = $this->apiClient->getOrderOperations($subscription->providerOrderId);
            } catch (ProviderApiException $e) {
                $warning = $e->getAdminMessage();
            }
        }

        return [
            'subscription' => $subscription,
            'plan' => $this->planRepository->find($subscription->planId),
            'payments' => $this->paymentRepository->findBySubscription($subscription->id),
            'operations' => $operations,
            'warning' => $warning,
        ];
    }
}
