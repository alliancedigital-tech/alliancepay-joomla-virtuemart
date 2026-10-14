<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SubscriptionStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use RuntimeException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class SubscriptionCancelService
{
    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionStatusChanger $statusChanger,
        private readonly AuditLogRepository $auditLog,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $subscriptionId
     * @param int $userId
     * @return Subscription
     */
    public function cancelByCustomer(int $subscriptionId, int $userId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if ($subscription === null || $userId < 1 || $subscription->virtuemartUserId !== $userId) {
            throw new RuntimeException('Subscription not found');
        }

        return $this->cancel($subscription, $userId);
    }

    /**
     * @throws RuntimeException
     * @throws ProviderApiException
     */
    public function cancel(Subscription $subscription, ?int $actingUserId = null): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            return $subscription;
        }

        if (!in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Pending], true)
            || $subscription->providerOrderId === null
        ) {
            throw new RuntimeException('The subscription cannot be cancelled in its current state');
        }

        $this->apiClient->cancelSubscriptionOrder([
            'hppOrderId' => $subscription->providerOrderId,
            'merchantId' => $this->config->getMerchantId(),
        ]);

        $this->statusChanger->change($subscription, SubscriptionStatus::Canceled);
        $this->auditLog->log(
            AuditLogRepository::ACTION_SUBSCRIPTION_CANCELED,
            $subscription->planId,
            null,
            ['subscription_id' => $subscription->id, 'provider_order_id' => $subscription->providerOrderId],
            $actingUserId
        );

        return $this->subscriptionRepository->find($subscription->id) ?? $subscription;
    }
}
