<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationRowBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperation;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperationType;
use PHPUnit\Framework\TestCase;

class FiscalizationRowBuilderTest extends TestCase
{
    public function testButtonsFollowTheStatusTable(): void
    {
        $operations = [
            $this->purchase('none'),
            $this->purchase('pending'),
            $this->purchase('success'),
            $this->purchase('fail'),
            $this->purchase('error'),
        ];
        $records = [
            $this->record('pending', 'PENDING'),
            $this->record('success', 'SUCCESS', ['fiscal_number' => 'FN1', 'receipt_url' => 'https://kasa.example/r/1']),
            $this->record('fail', 'FAIL', ['error_code' => '541', 'error_description' => 'Помилка під час підписання']),
            $this->record('error', 'ERROR'),
        ];

        $rows = array_column((new FiscalizationRowBuilder())->build($operations, $records), null, 'operation_id');

        $this->assertSame([true, false], [$rows['none']['can_fiscalize'], $rows['none']['can_refresh']]);
        $this->assertNull($rows['none']['status']);
        $this->assertSame([false, true], [$rows['pending']['can_fiscalize'], $rows['pending']['can_refresh']]);
        $this->assertSame([false, false], [$rows['success']['can_fiscalize'], $rows['success']['can_refresh']]);
        $this->assertSame([false, true], [$rows['fail']['can_fiscalize'], $rows['fail']['can_refresh']]);
        $this->assertSame([true, false], [$rows['error']['can_fiscalize'], $rows['error']['can_refresh']]);
        $this->assertSame('FN1', $rows['success']['fiscal_number']);
        $this->assertSame('541', $rows['fail']['error_code']);
        $this->assertSame(120.5, $rows['none']['amount']);
    }

    public function testOnlyHttpReceiptLinksAreKept(): void
    {
        $rows = (new FiscalizationRowBuilder())->build(
            [$this->purchase('a'), $this->purchase('b')],
            [$this->record('a', 'SUCCESS', ['receipt_url' => 'javascript:alert(1)']), $this->record('b', 'SUCCESS', ['receipt_url' => 'HTTPS://kasa.example/r'])]
        );

        $this->assertNull($rows[0]['receipt_url']);
        $this->assertSame('HTTPS://kasa.example/r', $rows[1]['receipt_url']);
    }

    public function testRecordOfAnUnlistedOperationCanOnlyBeRechecked(): void
    {
        $rows = (new FiscalizationRowBuilder())->build([], [$this->record('gone', 'ERROR'), $this->record('gone2', 'PENDING')]);

        $this->assertFalse($rows[0]['can_fiscalize'], 'an unknown operation cannot be sent');
        $this->assertNull($rows[0]['amount']);
        $this->assertTrue($rows[1]['can_refresh']);
    }

    private function purchase(string $id): FiscalOperation
    {
        return new FiscalOperation(7, $id, FiscalOperationType::Purchase, 12050);
    }

    private function record(string $operationId, string $status, array $extra = []): array
    {
        return $extra + ['operation_id' => $operationId, 'operation_type' => 'PURCHASE', 'status' => $status];
    }
}
