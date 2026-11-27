<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductFiscalValuesMerger;
use Joomla\Database\DatabaseInterface;

/**
 * Reads the values of the "AlliancePay Fiscalization" custom field (vmcustom/alliancepay_fiscal) of a
 * product and, for a child product, of its parent.
 *
 * @since version 1.6.0
 */
class ProductFiscalAttributeRepository
{
    public const CUSTOM_ELEMENT = 'alliancepay_fiscal';

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ProductFiscalValuesMerger $merger
    ) {
    }

    /**
     * @return array<string, string> raw values keyed by FiscalValueValidator::FIELD_* (empty string = not set)
     */
    public function findRawValues(int $productId): array
    {
        $parentId = $this->findParentId($productId);
        $ids = array_values(array_unique(array_filter([$productId, $parentId])));

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('cf.virtuemart_product_id'),
                $this->db->quoteName('cf.customfield_params'),
                $this->db->quoteName('cf.disabler'),
                $this->db->quoteName('cf.noninheritable'),
            ])
            ->from($this->db->quoteName('#__virtuemart_product_customfields', 'cf'))
            ->join(
                'INNER',
                $this->db->quoteName('#__virtuemart_customs', 'c')
                . ' ON ' . $this->db->quoteName('c.virtuemart_custom_id') . ' = ' . $this->db->quoteName('cf.virtuemart_custom_id')
            )
            ->where($this->db->quoteName('c.custom_element') . ' = ' . $this->db->quote(self::CUSTOM_ELEMENT))
            ->where($this->db->quoteName('cf.published') . ' = 1')
            ->where($this->db->quoteName('cf.virtuemart_product_id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')')
            ->order($this->db->quoteName('cf.ordering') . ' ASC, ' . $this->db->quoteName('cf.virtuemart_customfield_id') . ' ASC');

        $this->db->setQuery($query);
        $rows = $this->db->loadAssocList() ?: [];

        return $this->merger->merge($rows, $productId, $parentId);
    }

    private function findParentId(int $productId): int
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('product_parent_id'))
            ->from($this->db->quoteName('#__virtuemart_products'))
            ->where($this->db->quoteName('virtuemart_product_id') . ' = ' . (int) $productId);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }
}
