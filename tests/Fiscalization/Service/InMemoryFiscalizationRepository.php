<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalizationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;

/**
 * Array-backed stand-in for the fiscalization table with the same insert / update semantics
 * (unique operation_id, null values never overwrite a column except the nullable error fields).
 */
class InMemoryFiscalizationRepository extends FiscalizationRepository
{
    /** @var array<int, array> */
    public array $rows = [];

    public function __construct()
    {
    }

    public function findByOperationId(string $operationId): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['operation_id'] === $operationId) {
                return $row;
            }
        }

        return null;
    }

    public function findByMerchantRequestId(string $merchantRequestId): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['merchant_request_id'] === $merchantRequestId) {
                return $row;
            }
        }

        return null;
    }

    public function findByOrderId(int $orderId): array
    {
        return array_values(array_filter($this->rows, static fn (array $row): bool => $row['order_id'] === $orderId));
    }

    public function insert(array $row): int
    {
        if ($this->findByOperationId($row['operation_id']) !== null) {
            throw new DuplicateKeyException('duplicate');
        }

        $id = count($this->rows) + 1;
        $this->rows[$id] = $row + ['id' => $id, 'bucket_id' => null, 'fiscal_number' => null, 'receipt_url' => null,
            'fiscalization_date_time' => null, 'fiscalization_modification_date_time' => null, 'modification_date_time' => null,
            'error_code' => null, 'error_description' => null, 'response_payload' => null, 'callback_payload' => null];

        return $id;
    }

    public function update(int $id, array $fields): void
    {
        foreach ($fields as $column => $value) {
            if ($value !== null || in_array($column, self::NULLABLE, true)) {
                $this->rows[$id][$column] = $value;
            }
        }
    }
}
