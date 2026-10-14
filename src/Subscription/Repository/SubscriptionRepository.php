<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
class SubscriptionRepository extends AbstractRepository
{
    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_subscription';
    }

    public function find(int $id): ?Subscription
    {
        $row = $this->fetchRow('id', $id);

        return $row ? Subscription::fromRow($row) : null;
    }

    /**
     * @param string $providerOrderId
     * @param bool $lock
     * @return Subscription|null
     */
    public function findByProviderOrderId(string $providerOrderId, bool $lock = false): ?Subscription
    {
        if ($providerOrderId === '') {
            return null;
        }

        $row = $this->fetchRow('provider_order_id', $providerOrderId, $lock);

        return $row ? Subscription::fromRow($row) : null;
    }

    public function findByVirtuemartOrderId(int $orderId): ?Subscription
    {
        $row = $this->fetchRow('virtuemart_order_id', $orderId);

        return $row ? Subscription::fromRow($row) : null;
    }

    /**
     * @return Subscription[]
     */
    public function findByUser(int $userId): array
    {
        return array_map([Subscription::class, 'fromRow'], $this->fetchAll('virtuemart_user_id', $userId, 'id', 'DESC'));
    }

    /**
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->order($this->db->quoteName('id') . ' DESC');
        $this->applyFilters($query, $filters);
        $this->db->setQuery($query, $offset, $limit);

        return array_map([Subscription::class, 'fromRow'], $this->db->loadAssocList() ?: []);
    }

    public function count(array $filters = []): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName($this->table()));
        $this->applyFilters($query, $filters);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    public function update(int $id, array $fields): void
    {
        $this->updateRow($id, $fields);
    }

    private function applyFilters(\Joomla\Database\QueryInterface $query, array $filters): void
    {
        if (!empty($filters['plan_id'])) {
            $query->where($this->db->quoteName('plan_id') . ' = ' . (int) $filters['plan_id']);
        }

        if (!empty($filters['status'])) {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote((string) $filters['status']));
        }

        if (!empty($filters['user_id'])) {
            $query->where($this->db->quoteName('virtuemart_user_id') . ' = ' . (int) $filters['user_id']);
        }
    }
}
