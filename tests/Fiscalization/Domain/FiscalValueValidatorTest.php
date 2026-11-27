<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalValueValidator as V;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalValueValidatorTest extends TestCase
{
    public function testTaxCode(): void
    {
        $this->assertNull(V::parseTaxCode(''));
        $this->assertSame(2, V::parseTaxCode(' 2 '));
        $this->assertSame(0, V::parseTaxCode('0'));
        $this->assertNull(V::parseTaxCode('abc'));
        $this->assertNull(V::parseTaxCode('-1'));
        $this->assertNull(V::taxCodeError(''));
        $this->assertNull(V::taxCodeError('8'));
        $this->assertSame(V::ERR_TAX_CODE, V::taxCodeError('2.5'));
        $this->assertSame(V::ERR_TAX_CODE, V::taxCodeError('x'));
    }

    public function testClassificationCode(): void
    {
        $this->assertSame('2203000000', V::parseClassificationCode(' 2203000000 '));
        $this->assertSame('2203', V::parseClassificationCode('2203'));
        $this->assertNull(V::parseClassificationCode('220'));
        $this->assertNull(V::parseClassificationCode('22030000001'));
        $this->assertNull(V::parseClassificationCode('22A3000000'));
        $this->assertNull(V::classificationCodeError(''));
        $this->assertSame(V::ERR_CLASSIFICATION_CODE, V::classificationCodeError('12ab'));
    }

    #[DataProvider('exciseProvider')]
    public function testExciseLabels(string $raw, array $expected, bool $valid): void
    {
        $this->assertSame($expected, V::parseExciseLabels($raw));
        $this->assertSame($valid ? null : V::ERR_EXCISE_LABELS, V::exciseLabelsError($raw));
    }

    public static function exciseProvider(): array
    {
        return [
            'empty' => ['', [], true],
            'comma' => ['AATE123456, BBTE654321', ['AATE123456', 'BBTE654321'], true],
            'lines' => ["AATE123456\nBBTE654321\r\n", ['AATE123456', 'BBTE654321'], true],
            'json-escaped newline from VirtueMart' => ['AATE123456\\nBBTE654321', ['AATE123456', 'BBTE654321'], true],
            'duplicates and blanks' => ['A1,,A1; ;B2', ['A1', 'B2'], true],
            'cyrillic series' => ['АБВ123456', ['АБВ123456'], true],
            'space inside a stamp' => ['AA TE123456', [], false],
            'forbidden character' => ['AATE|123', [], false],
        ];
    }

    public function testMeasurementCode(): void
    {
        $this->assertSame('0001', V::parseMeasurementCode(' 0001 '));
        $this->assertNull(V::parseMeasurementCode(''));
        $this->assertNull(V::parseMeasurementCode('bad value'));
        $this->assertSame(V::ERR_MEASUREMENT_REQUIRED, V::measurementCodeError('', true));
        $this->assertNull(V::measurementCodeError('', false));
        $this->assertSame(V::ERR_MEASUREMENT_CODE, V::measurementCodeError('a b', false));
    }

    public function testRequiresClassification(): void
    {
        foreach ([2, 7, 8] as $code) {
            $this->assertTrue(V::requiresClassification($code));
        }
        foreach ([null, 0, 1, 3, 9] as $code) {
            $this->assertFalse(V::requiresClassification($code));
        }
    }

    public function testValidDefaultsPass(): void
    {
        $this->assertSame([], V::validate([
            V::FIELD_TAX_CODE => '2',
            V::FIELD_CLASSIFICATION_CODE => '2203000000',
            V::FIELD_EXCISE_LABELS => 'AATE123456',
            V::FIELD_MEASUREMENT_CODE => '0001',
        ], true));
    }

    public function testClassificationRequiredForSpecialTaxCodes(): void
    {
        foreach (['2', '7', '8'] as $taxCode) {
            $this->assertSame(
                [V::ERR_CLASSIFICATION_REQUIRED],
                V::validate([V::FIELD_TAX_CODE => $taxCode, V::FIELD_MEASUREMENT_CODE => '0001'], true)
            );
        }

        $this->assertSame([], V::validate([V::FIELD_TAX_CODE => '1', V::FIELD_MEASUREMENT_CODE => '0001'], true));
    }

    public function testMeasurementRequiredOnlyForDefaults(): void
    {
        $this->assertSame([V::ERR_MEASUREMENT_REQUIRED], V::validate([], true));
        $this->assertSame([], V::validate([], false));
    }

    public function testProductLevelDoesNotDemandClassification(): void
    {
        // On a product the missing classification code can still come from the settings defaults.
        $this->assertSame([], V::validate([V::FIELD_TAX_CODE => '2'], false));
    }

    public function testAllErrorsAreReported(): void
    {
        $this->assertEqualsCanonicalizing(
            [V::ERR_TAX_CODE, V::ERR_CLASSIFICATION_CODE, V::ERR_EXCISE_LABELS, V::ERR_MEASUREMENT_CODE],
            V::validate([
                V::FIELD_TAX_CODE => 'x',
                V::FIELD_CLASSIFICATION_CODE => 'y',
                V::FIELD_EXCISE_LABELS => 'a b',
                V::FIELD_MEASUREMENT_CODE => 'z z',
            ], true)
        );
    }
}
