<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

use Joomla\CMS\Factory;

/**
 * Administrative actions journal for support investigations.
 *
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
class AuditLogRepository extends AbstractRepository
{
    public const ACTION_PLAN_CREATED = 'plan_created';
    public const ACTION_PLAN_SYNC_FAILED = 'plan_sync_failed';
    public const ACTION_PLAN_SYNC_RETRIED = 'plan_sync_retried';
    public const ACTION_PLAN_REPLACED = 'plan_replaced';
    public const ACTION_PLAN_REPRICED = 'plan_repriced';
    public const ACTION_PLAN_DEACTIVATED = 'plan_deactivated';
    public const ACTION_PLAN_ARCHIVE_CANDIDATE = 'plan_archive_candidate';
    public const ACTION_PLAN_ARCHIVED = 'plan_archived';
    public const ACTION_TEMPLATE_CREATED = 'template_created';
    public const ACTION_TEMPLATE_ARCHIVED = 'template_archived';
    public const ACTION_SUBSCRIPTION_CANCELED = 'subscription_canceled';
    public const ACTION_RUN_NOW = 'run_now';

    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_subscription_audit_log';
    }

    /**
     * @param string $action
     * @param int|null $planId
     * @param int|null $productId
     * @param array $details
     * @param int|null $userId
     * @return void
     */
    public function log(string $action, ?int $planId = null, ?int $productId = null, array $details = [], ?int $userId = null): void
    {
        $this->insertRow([
            'action' => $action,
            'plan_id' => $planId,
            'product_id' => $productId,
            'user_id' => $userId ?? $this->currentUserId(),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param int|null $planId
     * @return array
     */
    public function latest(int $limit = 100, int $offset = 0, ?int $planId = null): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->order($this->db->quoteName('id') . ' DESC');

        if ($planId !== null) {
            $query->where($this->db->quoteName('plan_id') . ' = ' . (int) $planId);
        }

        $this->db->setQuery($query, $offset, $limit);
        $rows = $this->db->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) $row['user_id'];
            $row['details'] = json_decode((string) $row['details'], true) ?: [];
        }

        return $rows;
    }

    /**
     * @return int
     */
    private function currentUserId(): int
    {
        try {
            return (int) Factory::getApplication()->getIdentity()->id;
        } catch (\Throwable) {
            return 0;
        }
    }
}
