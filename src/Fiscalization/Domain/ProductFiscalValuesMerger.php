<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Support\VmParams;

/**
 * Builds the product's raw fiscal values from its VirtueMart custom field rows, following VirtueMart's
 * inheritance: the product's own row first, then (for a child product) the parent's row, unless that
 * row is marked non-inheritable. A "disabler" on the product's own row switches the inherited values off.
 * Fields are merged one by one, so an empty field of the product is taken from the parent.
 *
 * Pure PHP: the rows come from ProductFiscalAttributeRepository.
 *
 * @since version 1.6.0
 */
final class ProductFiscalValuesMerger
{
    private const FIELDS = [
        FiscalValueValidator::FIELD_TAX_CODE,
        FiscalValueValidator::FIELD_CLASSIFICATION_CODE,
        FiscalValueValidator::FIELD_EXCISE_LABELS,
        FiscalValueValidator::FIELD_MEASUREMENT_CODE,
    ];

    /**
     * @param array<int, array<string, mixed>> $rows rows of #__virtuemart_product_customfields (keys
     *        virtuemart_product_id, customfield_params, disabler, noninheritable); published ones only
     *
     * @return array<string, string> raw values keyed by FiscalValueValidator::FIELD_*
     */
    public function merge(array $rows, int $productId, int $parentId): array
    {
        $values = array_fill_keys(self::FIELDS, '');
        $ownRows = $this->rowsOf($rows, $productId);
        $chain = $ownRows;

        $disabled = false;
        foreach ($ownRows as $row) {
            $disabled = $disabled || (int) ($row['disabler'] ?? 0) !== 0;
        }

        if (!$disabled && $parentId > 0 && $parentId !== $productId) {
            foreach ($this->rowsOf($rows, $parentId) as $row) {
                if ((int) ($row['noninheritable'] ?? 0) === 0) {
                    $chain[] = $row;
                }
            }
        }

        foreach ($chain as $row) {
            $params = VmParams::parse((string) ($row['customfield_params'] ?? ''));

            foreach (self::FIELDS as $field) {
                $value = $params[$field] ?? '';

                if ($values[$field] === '' && is_scalar($value) && trim((string) $value) !== '') {
                    $values[$field] = trim((string) $value);
                }
            }
        }

        return $values;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsOf(array $rows, int $productId): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['virtuemart_product_id'] ?? 0) === $productId
        ));
    }
}
