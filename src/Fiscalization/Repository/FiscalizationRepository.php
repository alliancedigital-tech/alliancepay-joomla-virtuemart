<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AbstractRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;

/**
 * Access to #__virtuemart_payment_plg_alliance_fiscalization. Rows are arrays; dates are UTC.
 *
 * @since version 1.6.0
 */
class FiscalizationRepository extends AbstractRepository
{
    /** Columns that may be set back to NULL by an update (e.g. an error that was resolved). */
    public const NULLABLE = ['error_code', 'error_description'];

    protected function table(): string
    {
        return '#__virtuemart_payment_plg_alliance_fiscalization';
    }

    public function findByOperationId(string $operationId): ?array
    {
        return $operationId === '' ? null : $this->cast($this->fetchRow('operation_id', $operationId));
    }

    public function findByMerchantRequestId(string $merchantRequestId): ?array
    {
        return $merchantRequestId === '' ? null : $this->cast($this->fetchRow('merchant_request_id', $merchantRequestId));
    }

    /**
     * @return array<int, array>
     */
    public function findByOrderId(int $orderId): array
    {
        return array_map(fn (array $row): array => $this->cast($row), $this->fetchAll('order_id', $orderId, 'id', 'ASC'));
    }

    /**
     * @throws DuplicateKeyException
     */
    public function insert(array $row): int
    {
        return $this->insertRow($row);
    }

    public function update(int $id, array $fields): void
    {
        $this->updateRow($id, $fields, self::NULLABLE);
    }

    private function cast(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        foreach (['id', 'order_id'] as $column) {
            $row[$column] = (int) $row[$column];
        }

        return $row;
    }
}
