<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\ScheduledPaymentStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SubscriptionStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionStatusChanger;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback
 *
 * @since version 1.5.0
 */
class SubscriptionCallbackHandler
{
    private const LOG_CATEGORY = 'alliance_payment';
    private const OPERATION_TYPE_REFUND = 'REFUND';

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ScheduledPaymentRepository $paymentRepository,
        private readonly PlanRepository $planRepository,
        private readonly SubscriptionStatusChanger $statusChanger,
        private readonly AmountVerifier $amountVerifier,
        private readonly DatabaseInterface $db
    ) {
    }

    /**
     * @param CallbackPayload $payload
     * @return CallbackResult
     * @throws Throwable
     */
    public function handle(CallbackPayload $payload): CallbackResult
    {
        $hppOrderId = $payload->getHppOrderId();

        if ($hppOrderId === '' || $this->subscriptionRepository->findByProviderOrderId($hppOrderId) === null) {
            return CallbackResult::notSubscription();
        }

        $this->db->transactionStart();

        try {
            $subscription = $this->subscriptionRepository->findByProviderOrderId($hppOrderId, true);

            if ($subscription === null) {
                $this->db->transactionRollback();

                return CallbackResult::notSubscription();
            }

            $vmOrderStatus = $this->apply($subscription, $payload);

            $this->subscriptionRepository->update(
                $subscription->id,
                ['callback_data' => json_encode(
                    $this->mergeCallbackData($subscription->callbackData, $payload),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )]
            );

            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();

            Log::add(
                'Subscription callback failed for hppOrderId=' . $hppOrderId . ': ' . $e->getMessage(),
                Log::ERROR,
                self::LOG_CATEGORY
            );

            throw $e;
        }

        return CallbackResult::handled($this->subscriptionRepository->find($subscription->id), $vmOrderStatus);
    }

    /**
     * Keeps every operation ever reported for the subscription (payments, retries, refunds): the newest
     * envelope wins for the top-level fields, operations are accumulated by operationId.
     *
     * @param array|null $existing
     * @param CallbackPayload $payload
     * @return array
     */
    private function mergeCallbackData(?array $existing, CallbackPayload $payload): array
    {
        $data = $payload->toArray();
        $incoming = [];

        if (isset($data['operations']) && is_array($data['operations'])) {
            $incoming = array_values(array_filter($data['operations'], 'is_array'));
        } elseif (isset($data['operation']) && is_array($data['operation'])) {
            $incoming = [$data['operation']];
        }

        unset($data['operation']);

        $merged = [];

        foreach (array_merge($existing['operations'] ?? [], $incoming) as $operation) {
            $key = (string) ($operation['operationId'] ?? '');
            $key = $key !== '' ? $key : 'n' . count($merged);
            $merged[$key] = $operation;
        }

        $data['operations'] = array_values($merged);

        return $data;
    }

    /**
     * @param Subscription $subscription
     * @param CallbackPayload $payload
     * @return string|null
     */
    private function apply(Subscription $subscription, CallbackPayload $payload): ?string
    {
        $operationType = strtoupper((string) ($payload->getOperation()['type'] ?? ''));

        if ($operationType === self::OPERATION_TYPE_REFUND) {
            Log::add(
                'Subscription callback: REFUND operation for subscription #' . $subscription->id . ' left to the refund flow',
                Log::INFO,
                self::LOG_CATEGORY
            );

            return CallbackResult::ORDER_STATUS_NONE;
        }

        $status = $payload->getPaymentStatus();

        if ($status === null) {
            Log::add(
                'Subscription callback: unrecognised status (orderStatus=' . var_export($payload->getOrderStatus(), true)
                . ', operation.status=' . var_export($payload->getOperationStatus(), true)
                . ') for subscription #' . $subscription->id,
                Log::WARNING,
                self::LOG_CATEGORY
            );
        }

        return $payload->isRecurring()
            ? $this->applyRecurring($subscription, $payload, $status)
            : $this->applyInitial($subscription, $payload, $status);
    }

    private function applyInitial(Subscription $subscription, CallbackPayload $payload, ?ScheduledPaymentStatus $status): ?string
    {
        $operationId = $payload->getOperationId();
        $row = ($operationId !== null ? $this->paymentRepository->findByOperationId($operationId) : null)
            ?? $this->paymentRepository->findInitialPayment($subscription->id);

        $this->savePayment($subscription, $row, $payload, $status, 1);
        $this->storeFutureSchedule($subscription, $payload);

        if ($status === ScheduledPaymentStatus::Success
            && in_array($subscription->status, [SubscriptionStatus::Pending, SubscriptionStatus::Failed], true)
        ) {
            $this->statusChanger->change($subscription, SubscriptionStatus::Active);

            return CallbackResult::ORDER_STATUS_SUCCESS;
        }

        if ($status === ScheduledPaymentStatus::Fail && $subscription->status === SubscriptionStatus::Pending) {
            $this->statusChanger->change($subscription, SubscriptionStatus::Failed);

            return CallbackResult::ORDER_STATUS_FAIL;
        }

        return CallbackResult::ORDER_STATUS_NONE;
    }

    private function applyRecurring(Subscription $subscription, CallbackPayload $payload, ?ScheduledPaymentStatus $status): ?string
    {
        $operationId = $payload->getOperationId();
        $scheduledPaymentId = $payload->getScheduledPaymentId();

        $row = $operationId !== null ? $this->paymentRepository->findByOperationId($operationId) : null;

        if ($row === null && $scheduledPaymentId !== null) {
            $row = $this->paymentRepository->findByScheduledPaymentId((string) $subscription->providerOrderId, $scheduledPaymentId);
        }

        $paymentNumber = $row['payment_number']
            ?? $payload->getPaymentNumber()
            ?? ($this->paymentRepository->maxPaymentNumber($subscription->id) + 1);

        $this->savePayment($subscription, $row, $payload, $status, (int) $paymentNumber);

        if ($status !== ScheduledPaymentStatus::Success) {
            return CallbackResult::ORDER_STATUS_NONE;
        }

        $vmOrderStatus = CallbackResult::ORDER_STATUS_NONE;

        if ($subscription->status === SubscriptionStatus::Pending) {
            $this->statusChanger->change($subscription, SubscriptionStatus::Active);
            $subscription = $this->subscriptionRepository->find($subscription->id) ?? $subscription;
            $vmOrderStatus = CallbackResult::ORDER_STATUS_SUCCESS;
        }

        $lastPayment = $subscription->getLastPaymentNumber();

        if ($lastPayment !== null && (int) $paymentNumber >= $lastPayment && $subscription->status === SubscriptionStatus::Active) {
            $this->statusChanger->change($subscription, SubscriptionStatus::Completed);
        }

        return $vmOrderStatus;
    }

    /**
     * @param Subscription $subscription
     * @param array|null $row
     * @param CallbackPayload $payload
     * @param ScheduledPaymentStatus|null $status
     * @param int $paymentNumber
     * @return void
     */
    private function savePayment(
        Subscription $subscription,
        ?array $row,
        CallbackPayload $payload,
        ?ScheduledPaymentStatus $status,
        int $paymentNumber
    ): void {
        $now = Factory::getDate()->toSql(false, $this->db);
        $coinAmount = $payload->getCoinAmount();

        $fields = [
            'operation_id' => $payload->getOperationId(),
            'scheduled_payment_id' => $payload->getScheduledPaymentId(),
            'coin_amount' => $coinAmount,
        ];

        $existingStatus = $row !== null ? ScheduledPaymentStatus::tryFrom((string) $row['status']) : null;
        $newStatus = $status;

        if ($existingStatus !== null && $existingStatus->isFinal() && $newStatus !== null && $newStatus !== $existingStatus) {
            $newStatus = $existingStatus;
        }

        if ($newStatus !== null) {
            $fields['status'] = $newStatus->value;

            if (in_array($newStatus, [ScheduledPaymentStatus::Success, ScheduledPaymentStatus::Fail], true)
                && ($row === null || empty($row['executed_at']))
            ) {
                $fields['executed_at'] = $now;
            }

            if ($newStatus === ScheduledPaymentStatus::Retry && $existingStatus !== ScheduledPaymentStatus::Retry) {
                $fields['retry_count'] = ((int) ($row['retry_count'] ?? 0)) + 1;
            }
        }

        if ($newStatus === ScheduledPaymentStatus::Success && $status === ScheduledPaymentStatus::Success) {
            $note = $this->amountVerifier->check(
                $subscription,
                $paymentNumber,
                $coinAmount,
                $this->planRepository->find($subscription->planId)?->rules
            );

            if ($note !== null) {
                $fields['needs_review'] = 1;
                $fields['review_note'] = $note;
                Log::add(
                    'Subscription #' . $subscription->id . ' amount mismatch, flagged for review: ' . $note,
                    Log::WARNING,
                    self::LOG_CATEGORY
                );
            }
        }

        if ($row !== null) {
            if (empty($row['payment_number'])) {
                $fields['payment_number'] = $paymentNumber;
            }

            $this->paymentRepository->update((int) $row['id'], $fields);

            return;
        }

        $this->paymentRepository->insert($fields + [
            'subscription_id' => $subscription->id,
            'provider_order_id' => (string) $subscription->providerOrderId,
            'payment_number' => $paymentNumber,
            'status' => ($newStatus ?? ScheduledPaymentStatus::Processing)->value,
        ]);
    }

    /**
     * @param Subscription $subscription
     * @param CallbackPayload $payload
     * @return void
     */
    private function storeFutureSchedule(Subscription $subscription, CallbackPayload $payload): void
    {
        foreach ($payload->getScheduledPayments() as $item) {
            try {
                if ($this->paymentRepository->findByScheduledPaymentId((string) $subscription->providerOrderId, $item['id']) !== null) {
                    continue;
                }

                $this->paymentRepository->insert([
                    'subscription_id' => $subscription->id,
                    'provider_order_id' => (string) $subscription->providerOrderId,
                    'scheduled_payment_id' => $item['id'],
                    'payment_number' => $item['number'],
                    'status' => ScheduledPaymentStatus::Waiting->value,
                    'scheduled_date' => $item['date'] !== null ? Factory::getDate($item['date'])->toSql(false, $this->db) : null,
                ]);
            } catch (DuplicateKeyException) {
                // Already stored by an earlier delivery.
            } catch (Throwable $e) {
                Log::add(
                    'Subscription #' . $subscription->id . ': cannot store scheduled payment ' . $item['id'] . ': ' . $e->getMessage(),
                    Log::WARNING,
                    self::LOG_CATEGORY
                );
            }
        }
    }
}
