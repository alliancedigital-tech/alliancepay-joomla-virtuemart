<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductFiscalValuesMerger;
use PHPUnit\Framework\TestCase;

class ProductFiscalValuesMergerTest extends TestCase
{
    private ProductFiscalValuesMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new ProductFiscalValuesMerger();
    }

    public function testOwnValues(): void
    {
        $values = $this->merger->merge([$this->row(10, 'tax_code="2"|measurement_code="0002"')], 10, 0);

        $this->assertSame('2', $values['tax_code']);
        $this->assertSame('0002', $values['measurement_code']);
        $this->assertSame('', $values['classification_code']);
        $this->assertSame('', $values['excise_labels']);
    }

    public function testNoRowsGiveEmptyValues(): void
    {
        $this->assertSame(
            ['tax_code' => '', 'classification_code' => '', 'excise_labels' => '', 'measurement_code' => ''],
            $this->merger->merge([], 10, 0)
        );
    }

    public function testChildFieldsFallBackToParentFieldByField(): void
    {
        $rows = [
            $this->row(10, 'tax_code="2"|classification_code=""'),
            $this->row(5, 'tax_code="7"|classification_code="2203000000"|measurement_code="0003"'),
        ];

        $values = $this->merger->merge($rows, 10, 5);

        $this->assertSame('2', $values['tax_code'], 'own value wins');
        $this->assertSame('2203000000', $values['classification_code'], 'empty own value falls back to the parent');
        $this->assertSame('0003', $values['measurement_code']);
    }

    public function testParentIsUsedWhenChildHasNoRow(): void
    {
        $values = $this->merger->merge([$this->row(5, 'tax_code="1"')], 10, 5);

        $this->assertSame('1', $values['tax_code']);
    }

    public function testNonInheritableParentRowIsIgnored(): void
    {
        $values = $this->merger->merge([$this->row(5, 'tax_code="1"', 0, 99)], 10, 5);

        $this->assertSame('', $values['tax_code']);
    }

    public function testDisablerOnOwnRowStopsInheritance(): void
    {
        $rows = [$this->row(10, 'measurement_code="0002"', 7), $this->row(5, 'tax_code="1"|measurement_code="0003"')];

        $values = $this->merger->merge($rows, 10, 5);

        $this->assertSame('', $values['tax_code']);
        $this->assertSame('0002', $values['measurement_code']);
    }

    public function testOtherProductsRowsAreIgnored(): void
    {
        $this->assertSame('', $this->merger->merge([$this->row(99, 'tax_code="2"')], 10, 5)['tax_code']);
    }

    public function testParentEqualToProductDoesNotDuplicate(): void
    {
        $this->assertSame('2', $this->merger->merge([$this->row(10, 'tax_code="2"')], 10, 10)['tax_code']);
    }

    private function row(int $productId, string $params, int $disabler = 0, int $nonInheritable = 0): array
    {
        return [
            'virtuemart_product_id' => $productId,
            'customfield_params' => $params,
            'disabler' => $disabler,
            'noninheritable' => $nonInheritable,
        ];
    }
}
