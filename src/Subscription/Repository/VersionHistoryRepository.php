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
class VersionHistoryRepository extends AbstractRepository
{
    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_plan_version_history';
    }

    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    /**
     * @return array<int, array>
     */
    public function findByPlan(int $planId): array
    {
        return array_map(
            static function (array $row): array {
                $row['id'] = (int) $row['id'];
                $row['plan_id'] = (int) $row['plan_id'];
                $row['created_by'] = (int) $row['created_by'];
                $row['old_rules'] = json_decode((string) $row['old_rules'], true) ?: [];
                $row['new_rules'] = json_decode((string) $row['new_rules'], true) ?: [];

                return $row;
            },
            $this->fetchAll('plan_id', $planId, 'id', 'DESC')
        );
    }
}
