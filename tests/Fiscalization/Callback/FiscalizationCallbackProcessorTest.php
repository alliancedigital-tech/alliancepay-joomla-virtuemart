<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Callback\FiscalizationCallbackProcessor as P;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalizationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service\FiscalizationRecordUpdater;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FiscalizationCallbackProcessorTest extends TestCase
{
    private const RECORD = ['id' => 5, 'order_id' => 7, 'operation_id' => 'op-1', 'merchant_request_id' => 'req-1', 'status' => 'PENDING'];

    public function testKnownOperationIsAppliedAsCallback(): void
    {
        $records = $this->createMock(FiscalizationRepository::class);
        $records->method('findByOperationId')->with('op-1')->willReturn(self::RECORD);
        $updater = $this->createMock(FiscalizationRecordUpdater::class);
        $updater->expects($this->once())->method('applyParsed')->with(
            self::RECORD,
            $this->callback(static fn (ParsedFiscalization $p): bool => $p->fiscalNumber === 'FN1'),
            ['operationId' => 'op-1', 'status' => 'SUCCESS', 'fiscalNumber' => 'FN1'],
            'callback'
        );

        $this->assertSame(P::HTTP_OK, (new P($records, $updater))->process(['operationId' => 'op-1', 'status' => 'SUCCESS', 'fiscalNumber' => 'FN1']));
    }

    public function testFallsBackToMerchantRequestId(): void
    {
        $records = $this->createMock(FiscalizationRepository::class);
        $records->method('findByOperationId')->willReturn(null);
        $records->method('findByMerchantRequestId')->with('req-1')->willReturn(self::RECORD);
        $updater = $this->createMock(FiscalizationRecordUpdater::class);
        $updater->expects($this->once())->method('applyParsed');

        $this->assertSame(P::HTTP_OK, (new P($records, $updater))->process(['operationId' => 'other', 'merchantRequestId' => 'req-1', 'status' => 'FAIL']));
    }

    public function testUnknownOperationIsAcknowledgedWithoutChanges(): void
    {
        $records = $this->createMock(FiscalizationRepository::class);
        $records->method('findByOperationId')->willReturn(null);
        $records->method('findByMerchantRequestId')->willReturn(null);
        $updater = $this->createMock(FiscalizationRecordUpdater::class);
        $updater->expects($this->never())->method('applyParsed');

        $this->assertSame(P::HTTP_OK, (new P($records, $updater))->process(['operationId' => 'nope', 'status' => 'SUCCESS']));
    }

    public function testUnusablePayloadsAreRejected(): void
    {
        $processor = new P($this->createMock(FiscalizationRepository::class), $this->createMock(FiscalizationRecordUpdater::class));

        $this->assertSame(P::HTTP_BAD_REQUEST, $processor->process([]));
        $this->assertSame(P::HTTP_BAD_REQUEST, $processor->process(['status' => 'SUCCESS']));
        $this->assertSame(P::HTTP_BAD_REQUEST, $processor->process(['operationId' => 'op-1']));
        $this->assertSame(P::HTTP_BAD_REQUEST, $processor->process(['operationId' => 'op-1', 'status' => 'bogus']));
    }

    public function testProcessingFailureAsksForRedelivery(): void
    {
        $records = $this->createMock(FiscalizationRepository::class);
        $records->method('findByOperationId')->willReturn(self::RECORD);
        $updater = $this->createMock(FiscalizationRecordUpdater::class);
        $updater->method('applyParsed')->willThrowException(new RuntimeException('db down'));

        $this->assertSame(P::HTTP_ERROR, (new P($records, $updater))->process(['operationId' => 'op-1', 'status' => 'SUCCESS']));
    }
}
