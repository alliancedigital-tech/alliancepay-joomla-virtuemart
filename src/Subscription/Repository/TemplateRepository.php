<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanTemplate;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\TemplateStatus;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
class TemplateRepository extends AbstractRepository
{
    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_plan_template';
    }

    public function find(int $id): ?PlanTemplate
    {
        $row = $this->fetchRow('id', $id);

        return $row ? PlanTemplate::fromRow($row) : null;
    }

    public function findByCode(string $code): ?PlanTemplate
    {
        $row = $this->fetchRow('code', $code);

        return $row ? PlanTemplate::fromRow($row) : null;
    }

    /**
     * @return PlanTemplate[]
     */
    public function all(bool $onlyActive = false): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->order($this->db->quoteName('name') . ' ASC');

        if ($onlyActive) {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote(TemplateStatus::Active->value));
        }

        $this->db->setQuery($query);

        return array_map([PlanTemplate::class, 'fromRow'], $this->db->loadAssocList() ?: []);
    }

    /**
     * @throws DuplicateKeyException on a duplicate code
     */
    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    public function update(int $id, array $fields): void
    {
        $this->updateRow($id, $fields);
    }
}
