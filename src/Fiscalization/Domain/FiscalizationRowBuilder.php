<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Joins an order's fiscalizable operations with their fiscalization records into the rows of the
 * "Fiscalization" block of the order page, including which buttons are available. Pure PHP.
 *
 * @since version 1.6.0
 */
final class FiscalizationRowBuilder
{
    /**
     * @param FiscalOperation[] $operations
     * @param array<int, array> $records fiscalization rows of the order
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(array $operations, array $records): array
    {
        $byOperation = [];

        foreach ($records as $record) {
            $byOperation[(string) $record['operation_id']] = $record;
        }

        $rows = [];

        foreach ($operations as $operation) {
            $record = $byOperation[$operation->operationId] ?? null;
            unset($byOperation[$operation->operationId]);
            $rows[] = $this->row(
                $operation->operationId,
                $operation->type->value,
                MoneyConverter::kopecksToUah($operation->amountKop),
                $record,
                true
            );
        }

        foreach ($byOperation as $operationId => $record) {
            // A record whose operation is no longer listed (e.g. its refund row changed): show it, but
            // only allow re-checking it.
            $rows[] = $this->row((string) $operationId, (string) $record['operation_type'], null, $record, false);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $operationId, string $type, ?float $amount, ?array $record, bool $operationKnown): array
    {
        $status = $record !== null ? FiscalizationStatus::tryFrom((string) $record['status']) : null;

        return [
            'operation_id' => $operationId,
            'type' => $type,
            'amount' => $amount,
            'status' => $status?->value,
            'fiscal_number' => $record['fiscal_number'] ?? null,
            'receipt_url' => self::safeUrl($record['receipt_url'] ?? null),
            'fiscalization_date_time' => $record['fiscalization_date_time'] ?? null,
            'error_code' => $record['error_code'] ?? null,
            'error_description' => $record['error_description'] ?? null,
            'can_fiscalize' => $operationKnown && FiscalizationStatus::canFiscalize($status),
            'can_refresh' => FiscalizationStatus::canRefresh($status),
        ];
    }

    private static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
