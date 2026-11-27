<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ApiErrorMapper;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue as I;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStateMachine as M;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStatus as S;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalizationResponseTest extends TestCase
{
    public function testParsesSuccessResponseAndConvertsDatesToUtc(): void
    {
        $parsed = ParsedFiscalization::fromArray([
            'status' => 'SUCCESS',
            'operationId' => 'op-1',
            'merchantRequestId' => 'req-1',
            'operationType' => 'PURCHASE',
            'bucketId' => 'b1',
            'fiscalNumber' => 'FN123',
            'receiptUrl' => 'https://kasa.example/r/1',
            'fiscalizationDateTime' => '2026-09-03T10:39:09.540+03:00',
            'fiscalizationModificationDateTime' => '2026-09-03T10:40:00+03:00',
            'modificationDateTime' => '2026-09-03T07:41:00Z',
        ]);

        $this->assertSame(S::Success, $parsed->status);
        $this->assertSame('FN123', $parsed->fiscalNumber);
        $this->assertSame('2026-09-03 07:39:09', $parsed->fiscalizationDateTime);
        $this->assertSame('2026-09-03 07:40:00', $parsed->fiscalizationModificationDateTime);
        $this->assertSame('2026-09-03 07:41:00', $parsed->modifiedAt());
    }

    public function testToleratesMissingAndMalformedFields(): void
    {
        $parsed = ParsedFiscalization::fromArray(['status' => 'weird', 'fiscalNumber' => '  ', 'fiscalizationDateTime' => 'not a date', 'receiptUrl' => ['x']]);

        $this->assertNull($parsed->status);
        $this->assertNull($parsed->fiscalNumber);
        $this->assertNull($parsed->fiscalizationDateTime);
        $this->assertNull($parsed->receiptUrl);
        $this->assertNull($parsed->modifiedAt());
    }

    public function testFailCarriesTheError(): void
    {
        $parsed = ParsedFiscalization::fromArray(['status' => 'fail', 'errorCode' => 541, 'errorDescription' => 'Помилка під час підписання']);

        $this->assertSame(S::Fail, $parsed->status);
        $this->assertSame('541', $parsed->errorCode);
    }

    public function testButtonRules(): void
    {
        $this->assertTrue(S::canFiscalize(null));
        $this->assertTrue(S::canFiscalize(S::Error));
        foreach ([S::Pending, S::Success, S::Fail] as $status) {
            $this->assertFalse(S::canFiscalize($status));
        }

        $this->assertTrue(S::canRefresh(S::Pending));
        $this->assertTrue(S::canRefresh(S::Fail));
        foreach ([null, S::Success, S::Error] as $status) {
            $this->assertFalse(S::canRefresh($status));
        }
    }

    #[DataProvider('transitions')]
    public function testStateMachine(?S $stored, S $incoming, ?string $storedAt, ?string $incomingAt, bool $expected): void
    {
        $this->assertSame($expected, M::shouldApply($stored, $incoming, $storedAt, $incomingAt));
    }

    public static function transitions(): array
    {
        $old = '2026-09-03 10:00:00';
        $new = '2026-09-03 10:05:00';

        return [
            'first data' => [null, S::Pending, null, null, true],
            'local error is replaced' => [S::Error, S::Fail, null, null, true],
            'pending to success' => [S::Pending, S::Success, null, null, true],
            'pending to fail' => [S::Pending, S::Fail, null, null, true],
            'success is not overwritten by pending' => [S::Success, S::Pending, $old, $new, false],
            'success is not overwritten by fail' => [S::Success, S::Fail, null, null, false],
            'success refresh with newer data' => [S::Success, S::Success, $old, $new, true],
            'success repeat without dates' => [S::Success, S::Success, null, null, true],
            'stale callback is ignored' => [S::Pending, S::Success, $new, $old, false],
            'same timestamp is applied' => [S::Pending, S::Success, $old, $old, true],
            'no dates: never lowers the status' => [S::Fail, S::Pending, null, null, false],
            'no dates: pending repeat' => [S::Pending, S::Pending, null, null, true],
            'only one date is known' => [S::Pending, S::Success, $old, null, true],
            'fail to success with newer data' => [S::Fail, S::Success, $old, $new, true],
        ];
    }

    public function testApiErrorMapping(): void
    {
        $this->assertEquals(new I(I::API_MISSING_FIELD, ['products']), ApiErrorMapper::toIssue('missing_required_field', ['products'], null));
        $this->assertEquals(new I(I::API_MISSING_FIELD, ['notificationUrl']), ApiErrorMapper::toIssue('missing_required_field', 'notificationUrl', null));
        $this->assertEquals(new I(I::API_INVALID_FORMAT, ['unitPrice']), ApiErrorMapper::toIssue('invalid_format', ['field' => 'unitPrice'], null));
        $this->assertEquals(new I(I::API_PAYMENT_NOT_FOUND), ApiErrorMapper::toIssue('payment_not_found', null, null));
        $this->assertEquals(new I(I::API_ORIGINAL_RECEIPT_NOT_FOUND), ApiErrorMapper::toIssue('original_receipt_not_found', [], null));
        $this->assertEquals(new I(I::API_ERROR, ['Boom']), ApiErrorMapper::toIssue('something_else', null, 'Boom'));
        $this->assertEquals(new I(I::API_ERROR, ['something_else']), ApiErrorMapper::toIssue('something_else', null, ''));
    }
}
