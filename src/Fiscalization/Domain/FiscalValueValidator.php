<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Parsing and validation of the fiscal product attributes (taxCode, classificationCode, exciseLabels,
 * measurementCode). Shared by the plugin settings (defaults) and the product-level fields, so both
 * accept exactly the same values. Pure PHP: no Joomla / VirtueMart dependencies.
 *
 * The parse* methods are lenient: an invalid value yields null / [] (so a malformed stored value is
 * treated as "not set"); use the *Error methods or validate() to report problems to the administrator.
 *
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain
 *
 * @since version 1.6.0
 */
final class FiscalValueValidator
{
    public const DEFAULT_MEASUREMENT_CODE = '0001';

    /** taxCode values for which classificationCode (and exciseLabels for excise goods) must be sent. */
    public const TAX_CODES_REQUIRING_CLASSIFICATION = [2, 7, 8];

    public const FIELD_TAX_CODE = 'tax_code';
    public const FIELD_CLASSIFICATION_CODE = 'classification_code';
    public const FIELD_EXCISE_LABELS = 'excise_labels';
    public const FIELD_MEASUREMENT_CODE = 'measurement_code';

    public const ERR_TAX_CODE = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_TAX_CODE';
    public const ERR_CLASSIFICATION_CODE = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_CLASSIFICATION_CODE';
    public const ERR_CLASSIFICATION_REQUIRED = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_CLASSIFICATION_REQUIRED';
    public const ERR_EXCISE_LABELS = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_EXCISE_LABELS';
    public const ERR_MEASUREMENT_CODE = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_MEASUREMENT_CODE';
    public const ERR_MEASUREMENT_REQUIRED = 'VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_MEASUREMENT_REQUIRED';

    private const PATTERN_TAX_CODE = '/^\d{1,4}$/';
    private const PATTERN_CLASSIFICATION_CODE = '/^\d{4,10}$/';
    private const PATTERN_MEASUREMENT_CODE = '/^[0-9A-Za-z_.-]{1,16}$/';
    private const PATTERN_EXCISE_LABEL = '/^[\p{L}\p{N}_-]{1,64}$/u';

    /**
     * Excise labels are separated by commas, semicolons or line breaks. VirtueMart stores a textarea
     * JSON-encoded, so a line break can come back as the two characters "\n"; those are separators too.
     * Spaces are NOT separators.
     */
    private const PATTERN_EXCISE_SEPARATOR = '/(?:\\\\[nrt]|[\r\n,;])+/';

    private function __construct()
    {
    }

    public static function requiresClassification(?int $taxCode): bool
    {
        return $taxCode !== null && in_array($taxCode, self::TAX_CODES_REQUIRING_CLASSIFICATION, true);
    }

    public static function taxCodeError(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' || preg_match(self::PATTERN_TAX_CODE, $raw) === 1 ? null : self::ERR_TAX_CODE;
    }

    public static function parseTaxCode(string $raw): ?int
    {
        $raw = trim($raw);

        return preg_match(self::PATTERN_TAX_CODE, $raw) === 1 ? (int) $raw : null;
    }

    public static function classificationCodeError(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' || preg_match(self::PATTERN_CLASSIFICATION_CODE, $raw) === 1
            ? null
            : self::ERR_CLASSIFICATION_CODE;
    }

    public static function parseClassificationCode(string $raw): ?string
    {
        $raw = trim($raw);

        return preg_match(self::PATTERN_CLASSIFICATION_CODE, $raw) === 1 ? $raw : null;
    }

    public static function exciseLabelsError(string $raw): ?string
    {
        foreach (self::splitExciseLabels($raw) as $label) {
            if (preg_match(self::PATTERN_EXCISE_LABEL, $label) !== 1) {
                return self::ERR_EXCISE_LABELS;
            }
        }

        return null;
    }

    /**
     * @return string[] valid labels only, trimmed, without empty items and duplicates
     */
    public static function parseExciseLabels(string $raw): array
    {
        $labels = array_filter(
            self::splitExciseLabels($raw),
            static fn (string $label): bool => preg_match(self::PATTERN_EXCISE_LABEL, $label) === 1
        );

        return array_values(array_unique($labels));
    }

    public static function measurementCodeError(string $raw, bool $required): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return $required ? self::ERR_MEASUREMENT_REQUIRED : null;
        }

        return preg_match(self::PATTERN_MEASUREMENT_CODE, $raw) === 1 ? null : self::ERR_MEASUREMENT_CODE;
    }

    public static function parseMeasurementCode(string $raw): ?string
    {
        $raw = trim($raw);

        return preg_match(self::PATTERN_MEASUREMENT_CODE, $raw) === 1 ? $raw : null;
    }

    /**
     * Validates a set of raw values (keys: the FIELD_* constants; missing keys count as empty).
     *
     * @param array<string, string> $raw
     * @param bool $measurementRequired true for the settings defaults, false for product-level fields
     *                                  (an empty product value falls back to the default)
     *
     * @return string[] language keys of the problems found, empty when the set is valid
     */
    public static function validate(array $raw, bool $measurementRequired): array
    {
        $taxCodeRaw = (string) ($raw[self::FIELD_TAX_CODE] ?? '');
        $classificationRaw = (string) ($raw[self::FIELD_CLASSIFICATION_CODE] ?? '');

        $errors = array_filter([
            self::taxCodeError($taxCodeRaw),
            self::classificationCodeError($classificationRaw),
            self::exciseLabelsError((string) ($raw[self::FIELD_EXCISE_LABELS] ?? '')),
            self::measurementCodeError((string) ($raw[self::FIELD_MEASUREMENT_CODE] ?? ''), $measurementRequired),
        ]);

        if (
            $measurementRequired
            && self::requiresClassification(self::parseTaxCode($taxCodeRaw))
            && trim($classificationRaw) === ''
        ) {
            $errors[] = self::ERR_CLASSIFICATION_REQUIRED;
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return string[]
     */
    private static function splitExciseLabels(string $raw): array
    {
        $parts = preg_split(self::PATTERN_EXCISE_SEPARATOR, $raw) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $label): bool => $label !== ''
        ));
    }
}
