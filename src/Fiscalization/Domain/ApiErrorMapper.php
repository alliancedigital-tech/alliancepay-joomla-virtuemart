<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Maps an HTTP 400 business error of the fiscalization endpoint (msgCode / msgAttrs) to an issue the
 * administrator can read. Pure PHP.
 *
 * @since version 1.6.0
 */
final class ApiErrorMapper
{
    private function __construct()
    {
    }

    /**
     * @param array<int|string, mixed>|string|null $msgAttrs
     */
    public static function toIssue(?string $msgCode, array|string|null $msgAttrs, ?string $msgText): FiscalizationIssue
    {
        $attribute = self::firstAttribute($msgAttrs);

        return match ($msgCode) {
            'missing_required_field' => new FiscalizationIssue(FiscalizationIssue::API_MISSING_FIELD, [$attribute]),
            'invalid_format' => new FiscalizationIssue(FiscalizationIssue::API_INVALID_FORMAT, [$attribute]),
            'payment_not_found' => new FiscalizationIssue(FiscalizationIssue::API_PAYMENT_NOT_FOUND),
            'original_receipt_not_found' => new FiscalizationIssue(FiscalizationIssue::API_ORIGINAL_RECEIPT_NOT_FOUND),
            default => new FiscalizationIssue(
                FiscalizationIssue::API_ERROR,
                [trim((string) $msgText) !== '' ? (string) $msgText : (string) $msgCode]
            ),
        };
    }

    private static function firstAttribute(array|string|null $msgAttrs): string
    {
        if (is_array($msgAttrs)) {
            foreach ($msgAttrs as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }

            return '';
        }

        return trim((string) $msgAttrs);
    }
}
