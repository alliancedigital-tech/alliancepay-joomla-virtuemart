<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperation;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperationType;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\RefundSelection;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Joomla\Database\DatabaseInterface;

/**
 * Finds the successful operations of an order that can be fiscalized:
 *  - PURCHASE of a one-off payment: operations[] of alliance_order.callback_data (type PURCHASE, SUCCESS);
 *  - PURCHASE of every successful subscription payment (the initial and the recurring ones);
 *  - REFUND: successful rows of alliance_refund.
 * A/2A, PREAUTH and COMPLETION operations are out of scope.
 *
 * @since version 1.6.0
 */
class FiscalOperationRepository
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ScheduledPaymentRepository $scheduledPaymentRepository
    ) {
    }

    /**
     * @return FiscalOperation[] purchases first, then refunds, each in creation order
     */
    public function listForOrder(int $orderId): array
    {
        return array_merge($this->purchases($orderId), $this->refunds($orderId));
    }

    public function find(int $orderId, string $operationId): ?FiscalOperation
    {
        foreach ($this->listForOrder($orderId) as $operation) {
            if ($operation->operationId === $operationId) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @return FiscalOperation[]
     */
    private function purchases(int $orderId): array
    {
        $operations = [];
        $subscription = $this->subscriptionRepository->findByVirtuemartOrderId($orderId);

        if ($subscription !== null) {
            foreach ($this->scheduledPaymentRepository->findBySubscription($subscription->id) as $payment) {
                if (($payment['status'] ?? '') === 'SUCCESS' && !empty($payment['operation_id']) && (int) ($payment['coin_amount'] ?? 0) > 0) {
                    $operations[] = new FiscalOperation(
                        $orderId,
                        (string) $payment['operation_id'],
                        FiscalOperationType::Purchase,
                        (int) $payment['coin_amount']
                    );
                }
            }

            $known = [];

            foreach ($operations as $existing) {
                $known[$existing->operationId] = true;
            }

            foreach ((array) ($subscription->callbackData['operations'] ?? []) as $operation) {
                $operationId = (string) ($operation['operationId'] ?? '');

                if (
                    ($operation['type'] ?? '') === 'PURCHASE'
                    && ($operation['status'] ?? '') === 'SUCCESS'
                    && $operationId !== ''
                    && !isset($known[$operationId])
                    && (int) ($operation['coinAmount'] ?? 0) > 0
                ) {
                    $known[$operationId] = true;
                    $operations[] = new FiscalOperation(
                        $orderId,
                        $operationId,
                        FiscalOperationType::Purchase,
                        (int) $operation['coinAmount']
                    );
                }
            }

            return $operations;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('callback_data'))
            ->from($this->db->quoteName('#__virtuemart_payment_plg_alliance_order'))
            ->where($this->db->quoteName('order_id') . ' = ' . (int) $orderId)
            ->setLimit(1);
        $this->db->setQuery($query);
        $callbackData = json_decode((string) $this->db->loadResult(), true);

        foreach ((array) ($callbackData['operations'] ?? []) as $operation) {
            if (
                ($operation['type'] ?? '') === 'PURCHASE'
                && ($operation['status'] ?? '') === 'SUCCESS'
                && !empty($operation['operationId'])
                && (int) ($operation['coinAmount'] ?? 0) > 0
            ) {
                $operations[] = new FiscalOperation(
                    $orderId,
                    (string) $operation['operationId'],
                    FiscalOperationType::Purchase,
                    (int) $operation['coinAmount']
                );
            }
        }

        return $operations;
    }

    /**
     * @return FiscalOperation[]
     */
    private function refunds(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select(['operation_id', 'coin_amount', 'original_operation_id', 'items'])
            ->from($this->db->quoteName('#__virtuemart_payment_plg_alliance_refund'))
            ->where($this->db->quoteName('order_id') . ' = ' . (int) $orderId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('SUCCESS'))
            ->order($this->db->quoteName('refund_id') . ' ASC');
        $this->db->setQuery($query);

        $operations = [];

        foreach ($this->db->loadAssocList() ?: [] as $row) {
            if ((string) $row['operation_id'] === '' || (int) $row['coin_amount'] <= 0) {
                continue;
            }

            $operations[] = new FiscalOperation(
                $orderId,
                (string) $row['operation_id'],
                FiscalOperationType::Refund,
                (int) $row['coin_amount'],
                (string) $row['original_operation_id'] !== '' ? (string) $row['original_operation_id'] : null,
                RefundSelection::fromJson($row['items'] ?? null)
            );
        }

        return $operations;
    }
}
