<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain
 *
 * @since version 1.5.0
 */
class PlanRulesBuilder
{
    public const STAGE_TRIAL = 'trial';
    public const STAGE_PAID = 'paid';

    /**
     * @param array $templateRules structure rows (see class doc)
     * @param int $coinAmount price in minor units for every `paid` stage
     * @param string $currency ISO 4217 numeric code, e.g. "980"
     *
     * @return array rules ready for RuleValidator / provider
     */
    public function build(array $templateRules, int $coinAmount, string $currency): array
    {
        $rules = [];

        foreach (array_values($templateRules) as $row) {
            $stage = ($row['stage'] ?? self::STAGE_PAID) === self::STAGE_TRIAL
                ? self::STAGE_TRIAL
                : self::STAGE_PAID;

            $rules[] = [
                'paymentNumberFrom' => $row['paymentNumberFrom'] ?? null,
                'paymentNumberTo' => $row['paymentNumberTo'] ?? null,
                'intervalValue' => $row['intervalValue'] ?? null,
                'intervalUnit' => $row['intervalUnit'] ?? null,
                'coinAmount' => $stage === self::STAGE_TRIAL ? 0 : $coinAmount,
                'currency' => $currency,
            ];
        }

        return $rules;
    }
}
