<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalAttributeResolver;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalDefaults;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue as I;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationRequestBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationValidationException;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalValueValidator as V;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductLine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ResolvedFiscalAttributes;
use PHPUnit\Framework\TestCase;

class FiscalizationRequestBuilderTest extends TestCase
{
    private const URL = 'https://shop.example/index.php?action=fiscalization_callback&onme=7';

    public function testBuildsTheDocumentedRequest(): void
    {
        $request = $this->build(
            [new ProductLine('42', 42, 'Товар 1', 2, 12000)],
            24000,
            fn (int $id): ResolvedFiscalAttributes => (new FiscalAttributeResolver())->resolve(
                [V::FIELD_TAX_CODE => '2', V::FIELD_CLASSIFICATION_CODE => '2203000000', V::FIELD_EXCISE_LABELS => 'AATE123456'],
                new FiscalDefaults()
            )
        );

        $this->assertSame(
            [
                'merchantRequestId' => 'req-1',
                'merchantId' => 'merchant-1',
                'operationId' => 'op-1',
                'receiptComment' => 'PAYMENT_FOR_ORDER_123',
                'notificationUrl' => self::URL,
                'products' => [[
                    'id' => '42',
                    'name' => 'Товар 1',
                    'count' => 2.0,
                    'unitPrice' => 120.0,
                    'measurementCode' => '0001',
                    'taxCode' => 2,
                    'classificationCode' => '2203000000',
                    'exciseLabels' => ['AATE123456'],
                ]],
            ],
            $request
        );
    }

    public function testNullFieldsAreOmitted(): void
    {
        $request = $this->build([new ProductLine('1', 1, 'A', 1, 5000)], 5000, $this->defaultsOnly());

        $this->assertSame(['id', 'name', 'count', 'unitPrice', 'measurementCode'], array_keys($request['products'][0]));
    }

    public function testEmptyCommentIsOmitted(): void
    {
        $request = $this->build([new ProductLine('1', 1, 'A', 1, 5000)], 5000, $this->defaultsOnly(), '');

        $this->assertArrayNotHasKey('receiptComment', $request);
    }

    public function testMissingNotificationUrlBlocksTheRequest(): void
    {
        $this->assertFirstIssue(I::NO_NOTIFICATION_URL, fn () => $this->build([new ProductLine('1', 1, 'A', 1, 5000)], 5000, $this->defaultsOnly(), 'c', ''));
    }

    public function testEmptyProductsBlockTheRequest(): void
    {
        $this->assertFirstIssue(I::NO_PRODUCTS, fn () => $this->build([], 0, $this->defaultsOnly()));
    }

    public function testMissingRequiredFieldsAreReported(): void
    {
        $this->assertFirstIssue(I::MISSING_FIELD, fn () => (new FiscalizationRequestBuilder())->build(
            '', 'm', 'op', null, self::URL, [new ProductLine('1', 1, 'A', 1, 5000)], 5000, $this->defaultsOnly()
        ));
    }

    public function testSpecialTaxCodeWithoutClassificationBlocksTheRequest(): void
    {
        $attributes = fn (int $id): ResolvedFiscalAttributes => (new FiscalAttributeResolver())->resolve(
            [V::FIELD_TAX_CODE => '7'],
            new FiscalDefaults()
        );

        $this->assertFirstIssue(I::CLASSIFICATION_REQUIRED_FOR_PRODUCT, fn () => $this->build([new ProductLine('1', 1, 'Вино', 1, 5000)], 5000, $attributes));
    }

    public function testSumMustMatchTheOperationAmount(): void
    {
        $this->assertFirstIssue(I::SUM_MISMATCH, fn () => $this->build([new ProductLine('1', 1, 'A', 1, 5000)], 5100, $this->defaultsOnly()));
    }

    public function testServiceLinesGetDefaultsOnly(): void
    {
        $asked = [];
        $attributes = function (int $id) use (&$asked): ResolvedFiscalAttributes {
            $asked[] = $id;

            return (new FiscalAttributeResolver())->resolve([], new FiscalDefaults(1, null, [], '0001'));
        };

        $request = $this->build(
            [new ProductLine('5', 5, 'A', 1, 5000), new ProductLine('5', 5, 'A', 2, 5001), new ProductLine('shipment', 0, 'Доставка', 1, 3000)],
            18002,
            $attributes
        );

        $this->assertSame([5, 0], $asked, 'attributes are looked up once per product, 0 for service lines');
        $this->assertSame(1, $request['products'][2]['taxCode']);
    }

    private function build(array $lines, int $operationKop, callable $attributes, ?string $comment = 'PAYMENT_FOR_ORDER_123', string $url = self::URL): array
    {
        return (new FiscalizationRequestBuilder())->build('req-1', 'merchant-1', 'op-1', $comment, $url, $lines, $operationKop, $attributes);
    }

    private function defaultsOnly(): callable
    {
        return fn (int $id): ResolvedFiscalAttributes => (new FiscalAttributeResolver())->resolve([], new FiscalDefaults());
    }

    private function assertFirstIssue(string $key, callable $action): void
    {
        try {
            $action();
            $this->fail('FiscalizationValidationException expected');
        } catch (FiscalizationValidationException $e) {
            $this->assertSame($key, $e->issues[0]->key);
        }
    }
}
