<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Turns the lines of a VirtueMart order into the amounts of one payment operation (UAH kopecks).
 *
 *  1. Order-level discounts (coupon, bill discount, negative fees) are what is left of the lines after
 *     comparing them with what the customer paid; they are spread over the items proportionally.
 *  2. All lines (discounted items, shipment, payment fee) are then scaled to the operation amount. This
 *     also converts a foreign order currency to UAH and covers an operation whose amount differs from
 *     the order total (a repriced recurring payment).
 *
 * Both steps leave the rounding remainder on the last line, so the lines add up to the operation amount.
 *
 * @since version 1.6.0
 */
final class OrderAmountAllocator
{
    /**
     * @return AllocatedLine[]
     *
     * @throws FiscalizationValidationException
     */
    public function allocate(OrderSnapshot $order, int $operationKop, string $shipmentName, string $paymentName): array
    {
        if ($operationKop <= 0) {
            throw FiscalizationValidationException::single(FiscalizationIssue::AMOUNT_ZERO);
        }

        $itemsKop = array_sum(array_map(static fn (OrderItemLine $item): int => max(0, $item->subtotalKop), $order->items));
        $shipmentKop = max(0, $order->shipmentKop);
        $paymentKop = max(0, $order->paymentKop);

        $discountKop = $itemsKop + $shipmentKop + $paymentKop - $order->orderTotalKop;
        $itemWeights = [];

        foreach ($order->items as $index => $item) {
            $itemWeights[$index] = max(0, $item->subtotalKop);
        }

        if ($discountKop > 0 && $itemsKop > 0) {
            $netItemsKop = max(0, $itemsKop - $discountKop);
            $itemWeights = $netItemsKop > 0 ? ProportionalAllocator::allocate($itemWeights, $netItemsKop) : array_fill_keys(array_keys($itemWeights), 0);
        }

        $weights = $itemWeights;

        if ($shipmentKop > 0) {
            $weights['shipment'] = $shipmentKop;
        }

        if ($paymentKop > 0) {
            $weights['payment'] = $paymentKop;
        }

        if (array_sum($weights) <= 0) {
            throw FiscalizationValidationException::single(FiscalizationIssue::AMOUNT_ZERO);
        }

        $shares = ProportionalAllocator::allocate($weights, $operationKop);
        $lines = [];

        foreach ($order->items as $index => $item) {
            $lines[] = new AllocatedLine(
                AllocatedLine::KIND_ITEM,
                $item->orderItemId,
                $item->productId,
                $item->sku,
                $item->name,
                $item->quantity,
                $shares[$index]
            );
        }

        if (isset($shares['shipment'])) {
            $lines[] = new AllocatedLine(AllocatedLine::KIND_SHIPMENT, 0, 0, '', $shipmentName, 1, $shares['shipment']);
        }

        if (isset($shares['payment'])) {
            $lines[] = new AllocatedLine(AllocatedLine::KIND_PAYMENT, 0, 0, '', $paymentName, 1, $shares['payment']);
        }

        return $lines;
    }
}
