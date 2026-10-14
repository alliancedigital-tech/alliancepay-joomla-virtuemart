<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
class ScheduledPaymentRepository extends AbstractRepository
{
    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_scheduled_payment';
    }

    public function find(int $id): ?array
    {
        $row = $this->fetchRow('id', $id);

        return $row ? self::cast($row) : null;
    }

    /**
     * @param string $operationId
     * @return array|null
     */
    public function findByOperationId(string $operationId): ?array
    {
        if ($operationId === '') {
            return null;
        }

        $row = $this->fetchRow('operation_id', $operationId);

        return $row ? self::cast($row) : null;
    }

    public function findByScheduledPaymentId(string $providerOrderId, string $scheduledPaymentId): ?array
    {
        if ($scheduledPaymentId === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('provider_order_id') . ' = ' . $this->db->quote($providerOrderId))
            ->where($this->db->quoteName('scheduled_payment_id') . ' = ' . $this->db->quote($scheduledPaymentId))
            ->setLimit(1);
        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        return is_array($row) ? self::cast($row) : null;
    }

    /**
     * @return array<int, array>
     */
    public function findBySubscription(int $subscriptionId): array
    {
        return array_map([self::class, 'cast'], $this->fetchAll('subscription_id', $subscriptionId, 'id', 'ASC'));
    }

    /**
     * @param int $subscriptionId
     * @return array|null
     */
    public function findInitialPayment(int $subscriptionId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('subscription_id') . ' = ' . (int) $subscriptionId)
            ->where($this->db->quoteName('payment_number') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC')
            ->setLimit(1);
        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        return is_array($row) ? self::cast($row) : null;
    }

    public function maxPaymentNumber(int $subscriptionId): int
    {
        $query = $this->db->getQuery(true)
            ->select('MAX(' . $this->db->quoteName('payment_number') . ')')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('subscription_id') . ' = ' . (int) $subscriptionId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @param int $subscriptionId
     * @return int
     */
    public function countSuccessful(int $subscriptionId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('subscription_id') . ' = ' . (int) $subscriptionId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('SUCCESS'));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @throws DuplicateKeyException
     */
    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    public function update(int $id, array $fields): void
    {
        $this->updateRow($id, $fields);
    }

    private static function cast(array $row): array
    {
        foreach (['id', 'subscription_id', 'payment_number', 'coin_amount', 'retry_count', 'needs_review'] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (int) $row[$column];
            }
        }

        return $row;
    }
}
