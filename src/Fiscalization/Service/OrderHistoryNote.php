<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\OrderSnapshotRepository;
use Joomla\CMS\Log\Log;
use Throwable;
use VmModel;

/**
 * Adds a service comment to the order history without changing the order status and without notifying
 * the customer (VirtueMart's _updateOrderHist only inserts a history row; no status events, no e-mails).
 *
 * @since version 1.6.0
 */
class OrderHistoryNote
{
    public function __construct(private readonly OrderSnapshotRepository $orders)
    {
    }

    public function add(int $orderId, string $comment): void
    {
        try {
            $status = $this->orders->findStatus($orderId);

            if ($status === null || !class_exists('VmModel')) {
                return;
            }

            VmModel::getModel('orders')->_updateOrderHist($orderId, $status, 0, $comment);
        } catch (Throwable $e) {
            Log::add('Fiscalization: cannot add a note to order ' . $orderId . ': ' . $e->getMessage(), Log::WARNING, 'alliance_payment');
        }
    }
}
