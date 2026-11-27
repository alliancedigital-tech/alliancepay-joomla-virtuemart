<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue as I;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationValidationException;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderItemLine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderSnapshot;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductLine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductsBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\RefundSelection;
use PHPUnit\Framework\TestCase;

class ProductsBuilderTest extends TestCase
{
    private ProductsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProductsBuilder();
    }

    public function testPlainOrderKeepsPricesAndAddsShipping(): void
    {
        // 2 x 120.00 + 1 x 50.50 + shipping 80.00 = 370.50
        $order = $this->order([[1, 5, 'A', 2, 24000], [2, 6, 'B', 1, 5050]], 8000, 0, 37050);

        $lines = $this->builder->buildPurchase($order, 37050, 'Shipping', 'Fee');

        $this->assertSame(
            [['5', 2, 12000], ['6', 1, 5050], ['shipment', 1, 8000]],
            $this->flatten($lines)
        );
        $this->assertSame(37050, $this->sum($lines));
    }

    public function testOrderLevelDiscountIsSpreadOverItemsOnly(): void
    {
        // items 300.00 (100 + 200), shipping 50.00, coupon 30.00 => customer pays 320.00
        $order = $this->order([[1, 5, 'A', 1, 10000], [2, 6, 'B', 1, 20000]], 5000, 0, 32000);

        $lines = $this->builder->buildPurchase($order, 32000, 'Shipping', 'Fee');

        // the 30.00 discount is split 1:2 over the items -> 90.00 and 180.00; shipping untouched
        $this->assertSame([['5', 1, 9000], ['6', 1, 18000], ['shipment', 1, 5000]], $this->flatten($lines));
        $this->assertSame(32000, $this->sum($lines));
    }

    public function testRoundingRemainderLandsOnTheLastLine(): void
    {
        // 3 identical items, 100.00 total split three ways
        $order = $this->order([[1, 5, 'A', 1, 3333], [2, 6, 'B', 1, 3333], [3, 7, 'C', 1, 3333]], 0, 0, 10000);

        $lines = $this->builder->buildPurchase($order, 10000, 'S', 'F');

        $this->assertSame(10000, $this->sum($lines));
        $this->assertSame([3333, 3333, 3334], array_map(static fn (ProductLine $l): int => $l->unitKop, $lines));
    }

    public function testLineThatDoesNotDivideByQuantityIsSplit(): void
    {
        // 3 pieces for 100.00 -> 33.33 x 2 + 33.34 x 1
        $order = $this->order([[1, 5, 'A', 3, 10000]], 0, 0, 10000);

        $lines = $this->builder->buildPurchase($order, 10000, 'S', 'F');

        $this->assertSame([['5', 2, 3333], ['5', 1, 3334]], $this->flatten($lines));
        $this->assertSame(10000, $this->sum($lines));
    }

    public function testForeignCurrencyOrderIsScaledToTheUahOperationAmount(): void
    {
        // order in USD: 2 x 10.00 + shipping 5.00 = 25.00 USD, paid as 1000.00 UAH
        $order = $this->order([[1, 5, 'A', 2, 2000]], 500, 0, 2500);

        $lines = $this->builder->buildPurchase($order, 100000, 'S', 'F');

        $this->assertSame([['5', 2, 40000], ['shipment', 1, 20000]], $this->flatten($lines));
    }

    public function testRepricedOperationAmountIsDistributedOverTheOrderItems(): void
    {
        $order = $this->order([[1, 5, 'A', 1, 10000], [2, 6, 'B', 1, 30000]], 0, 0, 40000);

        $lines = $this->builder->buildPurchase($order, 20000, 'S', 'F');

        $this->assertSame([['5', 1, 5000], ['6', 1, 15000]], $this->flatten($lines));
    }

    public function testPaymentFeeBecomesItsOwnLineAndNegativeFeeIsADiscount(): void
    {
        $order = $this->order([[1, 5, 'A', 1, 10000]], 0, 500, 10500);
        $this->assertSame(
            [['5', 1, 10000], ['payment', 1, 500]],
            $this->flatten($this->builder->buildPurchase($order, 10500, 'S', 'F'))
        );

        $discounted = $this->order([[1, 5, 'A', 1, 10000]], 0, -500, 9500);
        $this->assertSame(
            [['5', 1, 9500]],
            $this->flatten($this->builder->buildPurchase($discounted, 9500, 'S', 'F'))
        );
    }

    public function testFreeItemIsRejected(): void
    {
        $order = $this->order([[1, 5, 'Gift', 1, 0], [2, 6, 'B', 1, 10000]], 0, 0, 10000);

        $this->expectIssue(I::PRICE_NOT_POSITIVE, ['Gift'], fn () => $this->builder->buildPurchase($order, 10000, 'S', 'F'));
    }

    public function testZeroOperationAmountIsRejected(): void
    {
        $order = $this->order([[1, 5, 'A', 1, 10000]], 0, 0, 10000);

        $this->expectIssue(I::AMOUNT_ZERO, [], fn () => $this->builder->buildPurchase($order, 0, 'S', 'F'));
    }

    public function testPartialRefundUsesOriginalPricesOfTheReturnedUnits(): void
    {
        // original: 3 x 100.00 (A) + 1 x 50.00 (B) + shipping 30.00 = 380.00; return 2 x A = 200.00
        $order = $this->order([[1, 5, 'A', 3, 30000], [2, 6, 'B', 1, 5000]], 3000, 0, 38000);
        $selection = new RefundSelection([1 => 2]);

        $lines = $this->builder->buildRefund($order, 38000, $selection, 20000, 'S', 'F');

        $this->assertSame([['5', 2, 10000]], $this->flatten($lines));
    }

    public function testFullRefundWithShipping(): void
    {
        $order = $this->order([[1, 5, 'A', 3, 30000]], 3000, 0, 33000);
        $selection = new RefundSelection([1 => 3], true);

        $lines = $this->builder->buildRefund($order, 33000, $selection, 33000, 'S', 'F');

        $this->assertSame([['5', 3, 10000], ['shipment', 1, 3000]], $this->flatten($lines));
    }

    public function testRefundAmountThatDoesNotMatchTheReturnedItemsIsRejected(): void
    {
        $order = $this->order([[1, 5, 'A', 3, 30000]], 0, 0, 30000);

        $this->expectIssue(
            I::SUM_MISMATCH,
            ['100.00', '150.00'],
            fn () => $this->builder->buildRefund($order, 30000, new RefundSelection([1 => 1]), 15000, 'S', 'F')
        );
    }

    public function testRefundOfMoreThanBoughtOrUnknownItemIsRejected(): void
    {
        $order = $this->order([[1, 5, 'A', 2, 20000]], 0, 0, 20000);

        $this->expectIssue(I::REFUND_QUANTITY, ['A'], fn () => $this->builder->buildRefund($order, 20000, new RefundSelection([1 => 3]), 30000, 'S', 'F'));
        $this->expectIssue(I::REFUND_ITEM_UNKNOWN, [99], fn () => $this->builder->buildRefund($order, 20000, new RefundSelection([1 => 1, 99 => 1]), 10000, 'S', 'F'));
    }

    public function testRefundSelectionJsonRoundTrip(): void
    {
        $selection = RefundSelection::fromJson('{"items":[{"order_item_id":12,"product_id":5,"quantity":2}],"shipment":true}');

        $this->assertSame([12 => 2], $selection->quantities);
        $this->assertTrue($selection->shipment);
        $this->assertFalse($selection->payment);
        $this->assertNull(RefundSelection::fromJson(''));
        $this->assertNull(RefundSelection::fromJson('{"items":[]}'));
        $this->assertNull(RefundSelection::fromJson('not json'));
        $this->assertEquals($selection, RefundSelection::fromJson($selection->toJson([12 => 5])));
    }

    public function testSelectionForPartialAndFullRefundOfAdminButtons(): void
    {
        $order = $this->order([[1, 5, 'A', 3, 30000], [2, 6, 'B', 1, 5000], [3, 7, 'C', 2, 4000]], 3000, 0, 42000);

        $partial = RefundSelection::forOrder($order, [1, 3]);
        $this->assertSame([1 => 3, 3 => 2], $partial->quantities, 'whole lines in their full quantity');
        $this->assertFalse($partial->shipment);
        $this->assertFalse($partial->payment);

        $full = RefundSelection::forOrder($order, null);
        $this->assertSame([1 => 3, 2 => 1, 3 => 2], $full->quantities);
        $this->assertTrue($full->shipment);
        $this->assertTrue($full->payment);

        $this->assertTrue(RefundSelection::forOrder($order, [99])->isEmpty());
    }

    public function testFullRefundSelectionReproducesThePurchaseLines(): void
    {
        $order = $this->order([[1, 5, 'A', 3, 30000], [2, 6, 'B', 1, 5000]], 3000, 0, 38000);

        $purchase = $this->builder->buildPurchase($order, 38000, 'S', 'F');
        $refund = $this->builder->buildRefund($order, 38000, RefundSelection::forOrder($order, null), 38000, 'S', 'F');

        $this->assertSame($this->flatten($purchase), $this->flatten($refund));
    }

    /**
     * @param array<int, array{0:int,1:int,2:string,3:int,4:int}> $items [orderItemId, productId, name, qty, subtotalKop]
     */
    private function order(array $items, int $shipmentKop, int $paymentKop, int $totalKop): OrderSnapshot
    {
        return new OrderSnapshot(
            1,
            'N1',
            array_map(static fn (array $i): OrderItemLine => new OrderItemLine($i[0], $i[1], 'sku' . $i[1], $i[2], $i[3], $i[4]), $items),
            $shipmentKop,
            $paymentKop,
            $totalKop
        );
    }

    /**
     * @param ProductLine[] $lines
     */
    private function flatten(array $lines): array
    {
        return array_map(static fn (ProductLine $l): array => [$l->id, $l->count, $l->unitKop], $lines);
    }

    /**
     * @param ProductLine[] $lines
     */
    private function sum(array $lines): int
    {
        return array_sum(array_map(static fn (ProductLine $l): int => $l->totalKop(), $lines));
    }

    private function expectIssue(string $key, array $params, callable $action): void
    {
        try {
            $action();
            $this->fail('FiscalizationValidationException expected');
        } catch (FiscalizationValidationException $e) {
            $this->assertSame($key, $e->issues[0]->key);
            $this->assertEquals($params, $e->issues[0]->params);
        }
    }
}
