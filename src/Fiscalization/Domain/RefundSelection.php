<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * What a refund returns: quantities per order item and whether the shipment / payment fee are returned.
 * Stored as JSON in alliance_refund.items:
 * {"items":[{"order_item_id":12,"product_id":5,"quantity":2}],"shipment":true,"payment":false}
 *
 * @since version 1.6.0
 */
final class RefundSelection
{
    /**
     * @param array<int, int> $quantities order_item_id => quantity
     */
    public function __construct(
        public readonly array $quantities,
        public readonly bool $shipment = false,
        public readonly bool $payment = false
    ) {
    }

    /**
     * The selection a refund made from the admin buttons returns: the chosen order items in their full
     * quantity (the partial-refund form selects whole lines), or - when $itemIds is null (full refund) -
     * every item plus the shipment and the payment fee.
     *
     * @param int[]|null $itemIds order item ids of a partial refund; null for a full refund
     */
    public static function forOrder(OrderSnapshot $order, ?array $itemIds): self
    {
        $quantities = [];

        foreach ($order->items as $item) {
            if ($itemIds === null || in_array($item->orderItemId, $itemIds, true)) {
                $quantities[$item->orderItemId] = $item->quantity;
            }
        }

        return new self($quantities, $itemIds === null, $itemIds === null);
    }

    public static function fromJson(?string $json): ?self
    {
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        if (!is_array($data)) {
            return null;
        }

        $quantities = [];

        foreach ((array) ($data['items'] ?? []) as $item) {
            $id = (int) ($item['order_item_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($id > 0 && $quantity > 0) {
                $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
            }
        }

        $selection = new self($quantities, !empty($data['shipment']), !empty($data['payment']));

        return $selection->isEmpty() ? null : $selection;
    }

    public function isEmpty(): bool
    {
        return $this->quantities === [] && !$this->shipment && !$this->payment;
    }

    public function toJson(array $productIds = []): string
    {
        $items = [];

        foreach ($this->quantities as $orderItemId => $quantity) {
            $items[] = [
                'order_item_id' => $orderItemId,
                'product_id' => (int) ($productIds[$orderItemId] ?? 0),
                'quantity' => $quantity,
            ];
        }

        return (string) json_encode(['items' => $items, 'shipment' => $this->shipment, 'payment' => $this->payment]);
    }
}
