<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Plan;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SubscriptionStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SyncStatus;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
class PlanRepository extends AbstractRepository
{
    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_subscription_plan';
    }

    public function find(int $id): ?Plan
    {
        $row = $this->fetchRow('id', $id);

        return $row ? Plan::fromRow($row) : null;
    }

    public function findByPlanKey(string $planKey): ?Plan
    {
        $row = $this->fetchRow('plan_key', $planKey);

        return $row ? Plan::fromRow($row) : null;
    }

    public function findByProviderPlanId(string $providerPlanId): ?Plan
    {
        $row = $this->fetchRow('provider_plan_id', $providerPlanId);

        return $row ? Plan::fromRow($row) : null;
    }

    /**
     * @param int $productId
     * @param bool $onlyOfferable
     * @return array
     */
    public function findByProduct(int $productId, bool $onlyOfferable = false): array
    {
        $plans = array_map([Plan::class, 'fromRow'], $this->fetchAll('product_id', $productId, 'id'));

        return $onlyOfferable
            ? array_values(array_filter($plans, static fn (Plan $plan): bool => $plan->isOfferable()))
            : $plans;
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

        if (!empty($filters['product_id'])) {
            $query->where($this->db->quoteName('product_id') . ' = ' . (int) $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote((string) $filters['status']));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where($this->db->quoteName('is_active') . ' = ' . (int) $filters['is_active']);
        }

        $this->db->setQuery($query, $offset, $limit);

        return array_map([Plan::class, 'fromRow'], $this->db->loadAssocList() ?: []);
    }

    public function count(array $filters = []): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName($this->table()));

        if (!empty($filters['product_id'])) {
            $query->where($this->db->quoteName('product_id') . ' = ' . (int) $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote((string) $filters['status']));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where($this->db->quoteName('is_active') . ' = ' . (int) $filters['is_active']);
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @throws DuplicateKeyException on a duplicate plan_key / provider_plan_id
     */
    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    public function update(int $id, array $fields, array $nullable = []): void
    {
        $this->updateRow($id, $fields, $nullable);
    }

    /**
     * @param int $planId
     * @param int $delta
     * @return void
     */
    public function adjustActiveCount(int $planId, int $delta): void
    {
        $column = $this->db->quoteName('active_subscriptions_count');
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName($this->table()))
            ->set($column . ' = GREATEST(0, ' . $column . ' + ' . (int) $delta . ')')
            ->set($this->db->quoteName('updated_at') . ' = ' . $this->db->quote($this->now()))
            ->where($this->db->quoteName('id') . ' = ' . (int) $planId);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @param int $planId
     * @return int
     */
    public function recomputeActiveCount(int $planId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__virtuemart_payment_plg_alliance_subscription'))
            ->where($this->db->quoteName('plan_id') . ' = ' . (int) $planId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote(SubscriptionStatus::Active->value));
        $this->db->setQuery($query);
        $count = (int) $this->db->loadResult();

        $this->update($planId, ['active_subscriptions_count' => $count]);

        return $count;
    }

    /**
     * @return array
     */
    public function findArchiveCandidates(): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('is_active') . ' = 0')
            ->where($this->db->quoteName('active_subscriptions_count') . ' = 0')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote(PlanStatus::Active->value))
            ->where($this->db->quoteName('sync_status') . ' = ' . $this->db->quote(SyncStatus::Synced->value))
            ->where($this->db->quoteName('archive_candidate') . ' = 0');
        $this->db->setQuery($query);

        return array_map([Plan::class, 'fromRow'], $this->db->loadAssocList() ?: []);
    }

    /**
     * @param int $templateId
     * @return bool
     */
    public function isTemplateUsed(int $templateId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('template_id') . ' = ' . (int) $templateId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * @param int $productId
     * @return array
     */
    public function findOfferableForVariant(int $productId): array
    {
        $chain = $this->resolveAncestorChain($productId);

        if ($chain === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName('product_id') . ' IN (' . implode(',', $chain) . ')')
            ->where($this->db->quoteName('is_active') . ' = 1')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote(PlanStatus::Active->value))
            ->where($this->db->quoteName('sync_status') . ' = ' . $this->db->quote(SyncStatus::Synced->value));
        $this->db->setQuery($query);

        $plans = array_map([Plan::class, 'fromRow'], $this->db->loadAssocList() ?: []);
        $depth = array_flip($chain);
        usort($plans, static fn (Plan $a, Plan $b): int => ($depth[$a->productId] ?? PHP_INT_MAX) <=> ($depth[$b->productId] ?? PHP_INT_MAX));

        return $plans;
    }

    /**
     * @param int $planProductId
     * @param int $variantProductId
     * @return bool
     */
    public function isProductInPlanChain(int $planProductId, int $variantProductId): bool
    {
        return in_array($planProductId, $this->resolveAncestorChain($variantProductId), true);
    }

    /**
     * @param int $productId
     * @return bool
     */
    public function isSimpleProduct(int $productId): bool
    {
        return !$this->hasParent($productId) && !$this->hasChildren($productId);
    }

    public function hasParent(int $productId): bool
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('product_parent_id'))
            ->from($this->db->quoteName('#__virtuemart_products'))
            ->where($this->db->quoteName('virtuemart_product_id') . ' = ' . (int) $productId);
        $this->db->setQuery($query);
        $parentId = $this->db->loadResult();

        return $parentId !== null && (int) $parentId > 0;
    }

    public function hasChildren(int $productId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__virtuemart_products'))
            ->where($this->db->quoteName('product_parent_id') . ' = ' . (int) $productId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * @param int $productId
     * @param int $maxDepth
     * @return array
     */
    private function resolveAncestorChain(int $productId, int $maxDepth = 10): array
    {
        $chain = [];
        $current = $productId;

        for ($i = 0; $current > 0 && $i <= $maxDepth; $i++) {
            if (in_array($current, $chain, true)) {
                break;
            }

            $chain[] = $current;

            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('product_parent_id'))
                ->from($this->db->quoteName('#__virtuemart_products'))
                ->where($this->db->quoteName('virtuemart_product_id') . ' = ' . $current);
            $this->db->setQuery($query);
            $parentId = $this->db->loadResult();
            $current = $parentId !== null ? (int) $parentId : 0;
        }

        return $chain;
    }
}
