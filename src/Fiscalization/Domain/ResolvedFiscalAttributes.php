<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Fiscal values of one product after applying "product value, else default" to every field separately.
 *
 * @since version 1.6.0
 */
final class ResolvedFiscalAttributes
{
    /**
     * @param string[] $exciseLabels
     * @param string[] $errors language keys of unmet requirements (see FiscalValueValidator::ERR_*)
     */
    public function __construct(
        public readonly ?int $taxCode,
        public readonly ?string $classificationCode,
        public readonly array $exciseLabels,
        public readonly string $measurementCode,
        public readonly array $errors = []
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * The fiscal part of a products[] item: only fields that have a value, exciseLabels only when not empty.
     *
     * @return array<string, mixed>
     */
    public function toProductFields(): array
    {
        $fields = ['measurementCode' => $this->measurementCode];

        if ($this->taxCode !== null) {
            $fields['taxCode'] = $this->taxCode;
        }

        if ($this->classificationCode !== null) {
            $fields['classificationCode'] = $this->classificationCode;
        }

        if ($this->exciseLabels !== []) {
            $fields['exciseLabels'] = $this->exciseLabels;
        }

        return $fields;
    }
}
