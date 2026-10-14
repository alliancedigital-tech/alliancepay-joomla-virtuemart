<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Helper;

defined('_JEXEC') or die;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;

/**
 * @since 1.5.0
 */
final class RulesInput
{
    /**
     * @param mixed $rows
     * @param bool $withAmounts
     * @return array
     */
    public static function fromPost(mixed $rows, bool $withAmounts = true): array
    {
        $rules = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rule = [
                'paymentNumberFrom' => self::text($row['paymentNumberFrom'] ?? null),
                'paymentNumberTo' => self::text($row['paymentNumberTo'] ?? null),
                'intervalValue' => self::text($row['intervalValue'] ?? null),
                'intervalUnit' => self::text($row['intervalUnit'] ?? null),
            ];

            if ($withAmounts) {
                $amount = self::text($row['amount'] ?? null);
                $rule['coinAmount'] = $amount === null ? null : (string) (int) round((float) str_replace(',', '.', $amount) * 100);
                // Subscriptions are UAH-only (§ business rule) — enforced here server-side rather
                // than merely left unoffered in the UI, so it holds regardless of what a form post
                // actually contains.
                $rule['currency'] = (string) Config::SUBSCRIPTION_CURRENCY_CODE;
            } else {
                $rule['stage'] = ($row['stage'] ?? '') === 'trial' ? 'trial' : 'paid';
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @param array $rules
     * @return array
     */
    public static function toEditorRows(array $rules): array
    {
        return array_map(
            static function (array $rule): array {
                if (isset($rule['coinAmount'])) {
                    $rule['amount'] = number_format(((int) $rule['coinAmount']) / 100, 2, '.', '');
                }

                return $rule;
            },
            array_values($rules)
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
