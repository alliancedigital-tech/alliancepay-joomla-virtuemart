<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Builds the list of products[] lines (id, name, count, unit price) for a PURCHASE or REFUND. Pure PHP.
 *
 * Every line's count x unitPrice is exact: when a line total does not divide by its quantity, the line is
 * split in two entries whose unit prices differ by one kopeck (same id and name). The lines always add up
 * to the operation amount.
 *
 * @since version 1.6.0
 */
final class ProductsBuilder
{
    public function __construct(private readonly OrderAmountAllocator $allocator = new OrderAmountAllocator())
    {
    }

    /**
     * @return ProductLine[]
     *
     * @throws FiscalizationValidationException
     */
    public function buildPurchase(OrderSnapshot $order, int $operationKop, string $shipmentName, string $paymentName): array
    {
        $allocated = $this->allocator->allocate($order, $operationKop, $shipmentName, $paymentName);

        return $this->toProductLines(
            array_map(static fn (AllocatedLine $line): array => [$line, $line->quantity, $line->totalKop], $allocated)
        );
    }

    /**
     * @param int $originalKop amount of the original PURCHASE operation
     * @param int $refundKop amount of the REFUND operation
     *
     * @return ProductLine[]
     *
     * @throws FiscalizationValidationException
     */
    public function buildRefund(
        OrderSnapshot $order,
        int $originalKop,
        RefundSelection $selection,
        int $refundKop,
        string $shipmentName,
        string $paymentName
    ): array {
        if ($refundKop <= 0) {
            throw FiscalizationValidationException::single(FiscalizationIssue::AMOUNT_ZERO);
        }

        $original = $this->allocator->allocate($order, $originalKop, $shipmentName, $paymentName);
        $issues = [];
        $picked = [];

        foreach ($original as $line) {
            if ($line->kind === AllocatedLine::KIND_ITEM) {
                $quantity = $selection->quantities[$line->orderItemId] ?? 0;

                if ($quantity < 1) {
                    continue;
                }

                if ($quantity > $line->quantity) {
                    $issues[] = new FiscalizationIssue(FiscalizationIssue::REFUND_QUANTITY, [$line->name]);
                    continue;
                }

                $picked[] = [$line, $quantity, intdiv($line->totalKop * $quantity * 2 + $line->quantity, 2 * $line->quantity)];
            } elseif (
                ($line->kind === AllocatedLine::KIND_SHIPMENT && $selection->shipment)
                || ($line->kind === AllocatedLine::KIND_PAYMENT && $selection->payment)
            ) {
                $picked[] = [$line, 1, $line->totalKop];
            }
        }

        $knownItemIds = array_map(static fn (AllocatedLine $line): int => $line->orderItemId, $original);

        foreach (array_keys($selection->quantities) as $orderItemId) {
            if (!in_array($orderItemId, $knownItemIds, true)) {
                $issues[] = new FiscalizationIssue(FiscalizationIssue::REFUND_ITEM_UNKNOWN, [$orderItemId]);
            }
        }

        if ($issues !== []) {
            throw new FiscalizationValidationException($issues);
        }

        if ($picked === []) {
            throw FiscalizationValidationException::single(FiscalizationIssue::REFUND_ITEMS_MISSING);
        }

        $pickedSum = array_sum(array_column($picked, 2));

        // Rounding of per-unit shares may differ by a kopeck per line; more means the refund amount
        // does not match the returned items.
        if (abs($pickedSum - $refundKop) > count($picked)) {
            throw FiscalizationValidationException::single(
                FiscalizationIssue::SUM_MISMATCH,
                [number_format($pickedSum / 100, 2, '.', ''), number_format($refundKop / 100, 2, '.', '')]
            );
        }

        $weights = array_map(static fn (array $entry): int => $entry[2], $picked);
        $shares = ProportionalAllocator::allocate($weights, $refundKop);

        foreach ($picked as $index => $entry) {
            $picked[$index][2] = $shares[$index];
        }

        return $this->toProductLines($picked);
    }

    /**
     * @param array<int, array{0: AllocatedLine, 1: int, 2: int}> $entries line, quantity, total kopecks
     *
     * @return ProductLine[]
     *
     * @throws FiscalizationValidationException
     */
    private function toProductLines(array $entries): array
    {
        $result = [];
        $issues = [];

        foreach ($entries as [$line, $quantity, $totalKop]) {
            if ($totalKop < $quantity) {
                // Fewer kopecks than units: some unit would cost nothing.
                $issues[] = new FiscalizationIssue(FiscalizationIssue::PRICE_NOT_POSITIVE, [$line->name]);
                continue;
            }

            $id = match ($line->kind) {
                AllocatedLine::KIND_SHIPMENT => 'shipment',
                AllocatedLine::KIND_PAYMENT => 'payment',
                default => (string) ($line->productId > 0 ? $line->productId : ($line->sku !== '' ? $line->sku : $line->orderItemId)),
            };
            $unit = intdiv($totalKop, $quantity);
            $rest = $totalKop % $quantity;
            $pairs = $rest === 0 ? [[$quantity, $unit]] : [[$quantity - $rest, $unit], [$rest, $unit + 1]];

            foreach ($pairs as [$count, $unitKop]) {
                $result[] = new ProductLine($id, $line->productId, $line->name, $count, $unitKop);
            }
        }

        if ($issues !== []) {
            throw new FiscalizationValidationException($issues);
        }

        return $result;
    }
}
