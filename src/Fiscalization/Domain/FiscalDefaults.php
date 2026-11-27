<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Default fiscal values from the payment method settings.
 *
 * @since version 1.6.0
 */
final class FiscalDefaults
{
    /**
     * @param string[] $exciseLabels
     */
    public function __construct(
        public readonly ?int $taxCode = null,
        public readonly ?string $classificationCode = null,
        public readonly array $exciseLabels = [],
        public readonly string $measurementCode = FiscalValueValidator::DEFAULT_MEASUREMENT_CODE
    ) {
    }
}
