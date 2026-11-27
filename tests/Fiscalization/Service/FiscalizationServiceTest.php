<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalAttributeResolver;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalDefaults;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationRequestBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperation;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperationType;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderItemLine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\OrderSnapshot;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductsBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\RefundSelection;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Http\FiscalizationApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Http\FiscalizationApiResult;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalOperationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\OrderSnapshotRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service\FiscalizationService;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service\OrderHistoryNote;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service\ProductFiscalAttributeService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FiscalizationServiceTest extends TestCase
{
    private InMemoryFiscalizationRepository $records;
    private MockObject $operations;
    private MockObject $orders;
    private MockObject $client;
    private MockObject $note;
    private MockObject $config;
    private FiscalizationService $service;

    protected function setUp(): void
    {
        $this->records = new InMemoryFiscalizationRepository();
        $this->operations = $this->createMock(FiscalOperationRepository::class);
        $this->orders = $this->createMock(OrderSnapshotRepository::class);
        $this->client = $this->createMock(FiscalizationApiClient::class);
        $this->note = $this->createMock(OrderHistoryNote::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('isFiscalizationEnabled')->willReturn(true);
        $this->config->method('getMerchantId')->willReturn('merchant-1');

        $attributes = $this->createMock(ProductFiscalAttributeService::class);
        $attributes->method('resolveForProduct')->willReturnCallback(
            static fn (int $productId) => (new FiscalAttributeResolver())->resolve([], new FiscalDefaults())
        );
        $urls = $this->createMock(UrlProvider::class);
        $urls->method('getFiscalizationCallbackUrl')->willReturn('https://shop.example/cb');

        $this->service = new FiscalizationService(
            $this->records,
            $this->operations,
            $this->orders,
            new ProductsBuilder(),
            new FiscalizationRequestBuilder(),
            $attributes,
            $this->client,
            $this->note,
            $urls,
            $this->config
        );

        $this->orders->method('load')->willReturn(
            new OrderSnapshot(7, 'N7', [new OrderItemLine(1, 5, 'sku', 'Товар', 2, 24000)], 0, 0, 24000)
        );
        $this->operations->method('find')->willReturn(new FiscalOperation(7, 'op-1', FiscalOperationType::Purchase, 24000));
    }

    public function testFiscalizeSendsTheRequestAndStoresPending(): void
    {
        $sent = null;
        $this->client->method('create')->willReturnCallback(function (array $request) use (&$sent) {
            $sent = $request;

            return FiscalizationApiResult::success(['status' => 'PENDING', 'operationId' => 'op-1', 'bucketId' => 'b1'], 200);
        });

        $outcome = $this->service->fiscalize(7, 'op-1');

        $this->assertTrue($outcome->accepted);
        $this->assertSame(FiscalizationStatus::Pending, $outcome->status);
        $this->assertSame('op-1', $sent['operationId']);
        $this->assertSame('merchant-1', $sent['merchantId']);
        $this->assertSame('https://shop.example/cb', $sent['notificationUrl']);
        $this->assertSame('PAYMENT_FOR_ORDER_N7', $sent['receiptComment']);
        $this->assertSame(120.0, $sent['products'][0]['unitPrice']);

        $record = $this->records->findByOperationId('op-1');
        $this->assertSame('PENDING', $record['status']);
        $this->assertSame('b1', $record['bucket_id']);
        $this->assertSame($sent['merchantRequestId'], $record['merchant_request_id']);
        // json_encode writes 2.0 as 2: equal as numbers, which is all the API needs.
        $this->assertEquals($sent, json_decode($record['request_payload'], true));
    }

    public function testNothingIsSentWhenFiscalizationIsDisabled(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFiscalizationEnabled')->willReturn(false);
        $service = $this->serviceWith($config);
        $this->client->expects($this->never())->method('create');

        $outcome = $service->fiscalize(7, 'op-1');

        $this->assertFalse($outcome->accepted);
        $this->assertNull($this->records->findByOperationId('op-1'));
    }

    public function testBusinessErrorIsStoredAsErrorAndTheRetryReusesTheRequestId(): void
    {
        $this->client->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_MISSING_FIELD, ['products']), 400),
            FiscalizationApiResult::success(['status' => 'SUCCESS', 'fiscalNumber' => 'FN1'], 200)
        );

        $first = $this->service->fiscalize(7, 'op-1');
        $stored = $this->records->findByOperationId('op-1');

        $this->assertFalse($first->accepted);
        $this->assertSame('ERROR', $stored['status']);
        $this->assertStringContainsString('API_MISSING_FIELD', $stored['error_description']);

        $second = $this->service->fiscalize(7, 'op-1');
        $after = $this->records->findByOperationId('op-1');

        $this->assertTrue($second->accepted);
        $this->assertSame('SUCCESS', $after['status']);
        $this->assertSame('FN1', $after['fiscal_number']);
        $this->assertSame($stored['merchant_request_id'], $after['merchant_request_id'], 'idempotency key survives the retry');
        $this->assertNull($after['error_description'], 'the old error is cleared');
        $this->assertCount(1, $this->records->rows, 'upsert: still one record');
    }

    public function testLocalValidationErrorIsStoredAndNothingIsSent(): void
    {
        $orders = $this->createMock(OrderSnapshotRepository::class);
        $orders->method('load')->willReturn(
            new OrderSnapshot(7, 'N7', [new OrderItemLine(1, 5, 'sku', 'Подарунок', 1, 0), new OrderItemLine(2, 6, 'sku2', 'B', 1, 24000)], 0, 0, 24000)
        );
        $service = $this->serviceWith($this->config, $orders);
        $this->client->expects($this->never())->method('create');

        $outcome = $service->fiscalize(7, 'op-1');

        $this->assertFalse($outcome->accepted);
        $this->assertSame(FiscalizationStatus::Error, $outcome->status);
        $this->assertStringContainsString('PRICE_NOT_POSITIVE[Подарунок]', $outcome->message());
        $this->assertSame('ERROR', $this->records->findByOperationId('op-1')['status']);
    }

    public function testPendingAndSuccessfulRecordsCannotBeFiscalizedAgain(): void
    {
        $this->client->expects($this->once())->method('create')->willReturn(FiscalizationApiResult::success(['status' => 'PENDING'], 200));

        $this->service->fiscalize(7, 'op-1');
        $again = $this->service->fiscalize(7, 'op-1');

        $this->assertFalse($again->accepted);
        $this->assertSame(FiscalizationStatus::Pending, $again->status);
    }

    public function testRefreshResendsTheStoredRequestAndRecordsTheResultOnce(): void
    {
        $requests = [];
        $this->client->method('create')->willReturnCallback(function (array $request) use (&$requests) {
            $requests[] = $request;

            return count($requests) === 1
                ? FiscalizationApiResult::success(['status' => 'PENDING'], 200)
                : FiscalizationApiResult::success(['status' => 'SUCCESS', 'fiscalNumber' => 'FN9', 'receiptUrl' => 'https://kasa.example/r/9', 'fiscalizationDateTime' => '2026-09-03T10:39:09+03:00'], 200);
        });
        $this->note->expects($this->once())->method('add')->with(7, 'VMPAYMENT_ALLIANCEPAY_FISCAL_NOTE_SUCCESS[FN9]');

        $this->service->fiscalize(7, 'op-1');
        $refreshed = $this->service->refreshStatus(7, 'op-1');
        $this->service->refreshStatus(7, 'op-1'); // SUCCESS is final: no second request, no second note

        $this->assertCount(2, $requests);
        $this->assertEquals($requests[0], $requests[1], 'same body, same merchantRequestId');
        $this->assertSame(FiscalizationStatus::Success, $refreshed->status);
        $this->assertSame('2026-09-03 07:39:09', $this->records->findByOperationId('op-1')['fiscalization_date_time']);
    }

    public function testNetworkFailureWhileRefreshingKeepsPending(): void
    {
        $this->client->method('create')->willReturnOnConsecutiveCalls(
            FiscalizationApiResult::success(['status' => 'PENDING'], 200),
            FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['timeout']))
        );

        $this->service->fiscalize(7, 'op-1');
        $outcome = $this->service->refreshStatus(7, 'op-1');

        $this->assertFalse($outcome->accepted);
        $this->assertSame('PENDING', $this->records->findByOperationId('op-1')['status']);
    }

    public function testFailIsStoredWithTheErrorAndNotedOnce(): void
    {
        $this->client->method('create')->willReturn(
            FiscalizationApiResult::success(['status' => 'FAIL', 'errorCode' => '541', 'errorDescription' => 'Помилка під час підписання'], 200)
        );
        $this->note->expects($this->once())->method('add')->with(7, $this->stringContains('FISCAL_NOTE_FAIL'));

        $outcome = $this->service->fiscalize(7, 'op-1');
        $record = $this->records->findByOperationId('op-1');

        $this->assertSame(FiscalizationStatus::Fail, $outcome->status);
        $this->assertSame('541', $record['error_code']);
        $this->assertFalse($this->service->fiscalize(7, 'op-1')->accepted, 'FAIL cannot be re-sent, only re-checked');
    }

    public function testCallbackDataFollowsTheStateMachine(): void
    {
        $this->client->method('create')->willReturn(FiscalizationApiResult::success(['status' => 'PENDING'], 200));
        $this->service->fiscalize(7, 'op-1');
        $record = $this->records->findByOperationId('op-1');

        $success = ['operationId' => 'op-1', 'status' => 'SUCCESS', 'fiscalNumber' => 'FN1', 'modificationDateTime' => '2026-09-03T10:05:00Z'];
        $this->service->applyParsed($record, ParsedFiscalization::fromArray($success), $success, 'callback');
        $this->assertSame('SUCCESS', $this->records->findByOperationId('op-1')['status']);

        $late = ['operationId' => 'op-1', 'status' => 'PENDING', 'modificationDateTime' => '2026-09-03T10:00:00Z'];
        $this->service->applyParsed($this->records->findByOperationId('op-1'), ParsedFiscalization::fromArray($late), $late, 'callback');
        $stored = $this->records->findByOperationId('op-1');

        $this->assertSame('SUCCESS', $stored['status'], 'a stale PENDING must not overwrite SUCCESS');
        $this->assertSame('FN1', $stored['fiscal_number']);
        $this->assertSame(json_encode($late, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stored['callback_payload'], 'the delivery is still kept');
    }

    public function testRefundWithoutRecordedItemsIsReportedNotSent(): void
    {
        $operations = $this->createMock(FiscalOperationRepository::class);
        $operations->method('find')->willReturn(new FiscalOperation(7, 'refund-1', FiscalOperationType::Refund, 10000, 'op-1', null));
        $service = $this->serviceWith($this->config, null, $operations);
        $this->client->expects($this->never())->method('create');

        $outcome = $service->fiscalize(7, 'refund-1');

        $this->assertStringContainsString('REFUND_ITEMS_MISSING', $outcome->message());
    }

    public function testRefundIsBuiltFromTheOriginalPurchasePrices(): void
    {
        $operations = $this->createMock(FiscalOperationRepository::class);
        $operations->method('find')->willReturnMap([
            [7, 'refund-1', new FiscalOperation(7, 'refund-1', FiscalOperationType::Refund, 12000, 'op-1', new RefundSelection([1 => 1]))],
            [7, 'op-1', new FiscalOperation(7, 'op-1', FiscalOperationType::Purchase, 24000)],
        ]);
        $sent = null;
        $this->client->method('create')->willReturnCallback(function (array $request) use (&$sent) {
            $sent = $request;

            return FiscalizationApiResult::success(['status' => 'PENDING'], 200);
        });

        $this->serviceWith($this->config, null, $operations)->fiscalize(7, 'refund-1');

        $this->assertSame('REFUND_FOR_ORDER_N7', $sent['receiptComment']);
        $this->assertSame(1.0, $sent['products'][0]['count']);
        $this->assertSame(120.0, $sent['products'][0]['unitPrice']);
    }

    private function serviceWith(MockObject $config, ?MockObject $orders = null, ?MockObject $operations = null): FiscalizationService
    {
        $attributes = $this->createMock(ProductFiscalAttributeService::class);
        $attributes->method('resolveForProduct')->willReturnCallback(
            static fn (int $productId) => (new FiscalAttributeResolver())->resolve([], new FiscalDefaults())
        );
        $urls = $this->createMock(UrlProvider::class);
        $urls->method('getFiscalizationCallbackUrl')->willReturn('https://shop.example/cb');

        return new FiscalizationService(
            $this->records,
            $operations ?? $this->operations,
            $orders ?? $this->orders,
            new ProductsBuilder(),
            new FiscalizationRequestBuilder(),
            $attributes,
            $this->client,
            $this->note,
            $urls,
            $config
        );
    }
}
