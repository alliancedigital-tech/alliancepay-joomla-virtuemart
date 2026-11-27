<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalAttributeResolver;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalDefaults;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalValueValidator as V;
use PHPUnit\Framework\TestCase;

class FiscalAttributeResolverTest extends TestCase
{
    private FiscalAttributeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FiscalAttributeResolver();
    }

    public function testProductValuesWin(): void
    {
        $result = $this->resolver->resolve(
            [
                V::FIELD_TAX_CODE => '2',
                V::FIELD_CLASSIFICATION_CODE => '2203000000',
                V::FIELD_EXCISE_LABELS => 'AATE1,AATE2',
                V::FIELD_MEASUREMENT_CODE => '0002',
            ],
            new FiscalDefaults(1, '1111111111', ['DEF1'], '0001')
        );

        $this->assertSame(2, $result->taxCode);
        $this->assertSame('2203000000', $result->classificationCode);
        $this->assertSame(['AATE1', 'AATE2'], $result->exciseLabels);
        $this->assertSame('0002', $result->measurementCode);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyProductFallsBackToDefaults(): void
    {
        $result = $this->resolver->resolve([], new FiscalDefaults(1, '2203000000', ['DEF1'], '0005'));

        $this->assertSame(1, $result->taxCode);
        $this->assertSame('2203000000', $result->classificationCode);
        $this->assertSame(['DEF1'], $result->exciseLabels);
        $this->assertSame('0005', $result->measurementCode);
    }

    public function testMixedProductAndDefaultValues(): void
    {
        $result = $this->resolver->resolve(
            [V::FIELD_TAX_CODE => '2'],
            new FiscalDefaults(null, '2203000000', [], '0001')
        );

        $this->assertSame(2, $result->taxCode, 'from the product');
        $this->assertSame('2203000000', $result->classificationCode, 'from the defaults');
        $this->assertTrue($result->isValid());
    }

    public function testInvalidProductValueIsTreatedAsNotSet(): void
    {
        $result = $this->resolver->resolve(
            [V::FIELD_TAX_CODE => 'abc', V::FIELD_MEASUREMENT_CODE => 'bad value'],
            new FiscalDefaults(1, null, [], '0001')
        );

        $this->assertSame(1, $result->taxCode);
        $this->assertSame('0001', $result->measurementCode);
    }

    public function testNothingConfiguredOmitsOptionalFields(): void
    {
        $result = $this->resolver->resolve([], new FiscalDefaults());

        $this->assertNull($result->taxCode);
        $this->assertNull($result->classificationCode);
        $this->assertSame([], $result->exciseLabels);
        $this->assertSame('0001', $result->measurementCode);
        $this->assertSame(['measurementCode' => '0001'], $result->toProductFields());
        $this->assertTrue($result->isValid());
    }

    /**
     * @dataProvider specialTaxCodes
     */
    public function testClassificationRequiredForSpecialTaxCodes(int $taxCode): void
    {
        $result = $this->resolver->resolve([V::FIELD_TAX_CODE => (string) $taxCode], new FiscalDefaults());

        $this->assertFalse($result->isValid());
        $this->assertSame([V::ERR_CLASSIFICATION_REQUIRED], $result->errors);
    }

    public static function specialTaxCodes(): array
    {
        return [[2], [7], [8]];
    }

    public function testClassificationFromDefaultsSatisfiesSpecialTaxCode(): void
    {
        $result = $this->resolver->resolve(
            [V::FIELD_TAX_CODE => '8'],
            new FiscalDefaults(null, '2203000000')
        );

        $this->assertTrue($result->isValid());
    }

    public function testDefaultSpecialTaxCodeWithoutClassificationIsInvalid(): void
    {
        $result = $this->resolver->resolve([], new FiscalDefaults(7, null));

        $this->assertSame([V::ERR_CLASSIFICATION_REQUIRED], $result->errors);
    }

    public function testToProductFieldsContainsOnlyFilledFields(): void
    {
        $result = $this->resolver->resolve(
            [V::FIELD_TAX_CODE => '2', V::FIELD_CLASSIFICATION_CODE => '2203000000', V::FIELD_EXCISE_LABELS => 'AATE1'],
            new FiscalDefaults()
        );

        $this->assertSame(
            [
                'measurementCode' => '0001',
                'taxCode' => 2,
                'classificationCode' => '2203000000',
                'exciseLabels' => ['AATE1'],
            ],
            $result->toProductFields()
        );
    }
}
