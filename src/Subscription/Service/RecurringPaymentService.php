<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\ScheduledPaymentStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use RuntimeException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class RecurringPaymentService
{
    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ScheduledPaymentRepository $paymentRepository,
        private readonly AuditLogRepository $auditLog,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $scheduledPaymentRowId
     * @param int|null $userId
     * @return void
     */
    public function runNow(int $scheduledPaymentRowId, ?int $userId = null): void
    {
        $row = $this->paymentRepository->find($scheduledPaymentRowId);

        if ($row === null || empty($row['scheduled_payment_id'])) {
            throw new RuntimeException('Scheduled payment not found or has no provider id');
        }

        $status = ScheduledPaymentStatus::tryFrom((string) $row['status']);

        if ($status === ScheduledPaymentStatus::Success || $status === ScheduledPaymentStatus::Invalidated) {
            throw new RuntimeException('This scheduled payment is already settled or invalidated');
        }

        $subscription = $this->subscriptionRepository->find((int) $row['subscription_id']);

        if ($subscription === null || $subscription->providerOrderId === null) {
            throw new RuntimeException('Subscription not found');
        }

        $this->apiClient->triggerRecurringPayment([
            'hppOrderId' => $subscription->providerOrderId,
            'scheduledPaymentId' => (string) $row['scheduled_payment_id'],
            'merchantId' => $this->config->getMerchantId(),
        ]);

        $this->paymentRepository->update($scheduledPaymentRowId, ['status' => ScheduledPaymentStatus::Processing->value]);
        $this->auditLog->log(
            AuditLogRepository::ACTION_RUN_NOW,
            $subscription->planId,
            null,
            [
                'subscription_id' => $subscription->id,
                'scheduled_payment_row' => $scheduledPaymentRowId,
                'scheduled_payment_id' => $row['scheduled_payment_id'],
            ],
            $userId
        );
    }
}
