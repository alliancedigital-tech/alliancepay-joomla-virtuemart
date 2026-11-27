<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\RefundSelection;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\OrderSnapshotRepository;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * Produces the JSON stored in alliance_refund.items, which later tells fiscalization which items (and
 * quantities) a refund returned. It must never break a refund: on any problem it returns null and the
 * refund is stored without items (such a refund then cannot be fiscalized, with an explanatory message).
 *
 * @since version 1.6.0
 */
class RefundItemsBuilder
{
    public function __construct(private readonly OrderSnapshotRepository $orders)
    {
    }

    /**
     * @param int[]|null $itemIds order item ids of a partial refund; null for a full refund
     */
    public function build(int $orderId, ?array $itemIds): ?string
    {
        try {
            $order = $this->orders->load($orderId);

            if ($order === null) {
                return null;
            }

            $selection = RefundSelection::forOrder($order, $itemIds === null ? null : array_map('intval', $itemIds));

            if ($selection->isEmpty()) {
                return null;
            }

            $productIds = [];

            foreach ($order->items as $item) {
                $productIds[$item->orderItemId] = $item->productId;
            }

            return $selection->toJson($productIds);
        } catch (Throwable $e) {
            Log::add('Refund items could not be recorded for order ' . $orderId . ': ' . $e->getMessage(), Log::WARNING, 'alliance_payment');

            return null;
        }
    }
}
