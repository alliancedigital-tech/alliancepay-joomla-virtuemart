<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\MoneyConverter;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderItemLine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderSnapshot;
use Joomla\Database\DatabaseInterface;

/**
 * Loads the paid amounts of a VirtueMart order (order currency, kopecks).
 *
 * @since version 1.6.0
 */
class OrderSnapshotRepository
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function load(int $orderId): ?OrderSnapshot
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__virtuemart_orders'))
            ->where($this->db->quoteName('virtuemart_order_id') . ' = ' . (int) $orderId);
        $this->db->setQuery($query);
        $order = $this->db->loadAssoc();

        if (!is_array($order)) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__virtuemart_order_items'))
            ->where($this->db->quoteName('virtuemart_order_id') . ' = ' . (int) $orderId)
            ->order($this->db->quoteName('virtuemart_order_item_id') . ' ASC');
        $this->db->setQuery($query);

        $items = [];

        foreach ($this->db->loadAssocList() ?: [] as $row) {
            $items[] = new OrderItemLine(
                (int) $row['virtuemart_order_item_id'],
                (int) $row['virtuemart_product_id'],
                (string) $row['order_item_sku'],
                trim(strip_tags((string) $row['order_item_name'])),
                max(1, (int) $row['product_quantity']),
                MoneyConverter::toKopecks($row['product_subtotal_with_tax'])
            );
        }

        return new OrderSnapshot(
            $orderId,
            (string) $order['order_number'],
            $items,
            MoneyConverter::toKopecks($order['order_shipment']) + MoneyConverter::toKopecks($order['order_shipment_tax']),
            MoneyConverter::toKopecks($order['order_payment']) + MoneyConverter::toKopecks($order['order_payment_tax']),
            MoneyConverter::toKopecks($order['order_total'])
        );
    }

    public function findStatus(int $orderId): ?string
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('order_status'))
            ->from($this->db->quoteName('#__virtuemart_orders'))
            ->where($this->db->quoteName('virtuemart_order_id') . ' = ' . (int) $orderId);
        $this->db->setQuery($query);
        $status = $this->db->loadResult();

        return $status === null ? null : (string) $status;
    }
}
