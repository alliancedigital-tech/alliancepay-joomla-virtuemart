<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * The rule for choosing fiscal values, one place for automatic and manual fiscalization:
 * for every field separately - the product's own non-empty valid value, otherwise the default
 * from the settings, otherwise the field is omitted. If the resulting taxCode is 2, 7 or 8, the
 * classification code must be present.
 *
 * Pure PHP.
 *
 * @since version 1.6.0
 */
final class FiscalAttributeResolver
{
    /**
     * @param array<string, string> $productValues raw product values keyed by FiscalValueValidator::FIELD_*;
     *                                              missing / empty / invalid ones count as not set
     */
    public function resolve(array $productValues, FiscalDefaults $defaults): ResolvedFiscalAttributes
    {
        $taxCode = FiscalValueValidator::parseTaxCode((string) ($productValues[FiscalValueValidator::FIELD_TAX_CODE] ?? ''))
            ?? $defaults->taxCode;

        $classificationCode = FiscalValueValidator::parseClassificationCode(
            (string) ($productValues[FiscalValueValidator::FIELD_CLASSIFICATION_CODE] ?? '')
        ) ?? $defaults->classificationCode;

        $exciseLabels = FiscalValueValidator::parseExciseLabels(
            (string) ($productValues[FiscalValueValidator::FIELD_EXCISE_LABELS] ?? '')
        );

        if ($exciseLabels === []) {
            $exciseLabels = $defaults->exciseLabels;
        }

        $measurementCode = FiscalValueValidator::parseMeasurementCode(
            (string) ($productValues[FiscalValueValidator::FIELD_MEASUREMENT_CODE] ?? '')
        ) ?? $defaults->measurementCode;

        $errors = [];

        if (FiscalValueValidator::requiresClassification($taxCode) && $classificationCode === null) {
            $errors[] = FiscalValueValidator::ERR_CLASSIFICATION_REQUIRED;
        }

        return new ResolvedFiscalAttributes($taxCode, $classificationCode, $exciseLabels, $measurementCode, $errors);
    }
}
