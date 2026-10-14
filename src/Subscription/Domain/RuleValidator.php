<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain
 *
 * @since version 1.5.0
 */
class RuleValidator
{
    public const ERR_MISSING = 'missing';
    public const ERR_NOT_INTEGER = 'not_integer';
    public const ERR_NOT_POSITIVE = 'not_positive';
    public const ERR_NEGATIVE = 'negative';
    public const ERR_BAD_UNIT = 'bad_unit';
    public const ERR_BAD_CURRENCY = 'bad_currency';
    public const ERR_RANGE_ORDER = 'range_order';
    public const ERR_RANGE_START = 'range_start';
    public const ERR_RANGE_GAP_OR_OVERLAP = 'range_gap_or_overlap';
    public const ERR_NO_RULES = 'no_rules';

    public const INTERVAL_UNITS = ['DAY', 'WEEK', 'MONTH'];

    private const REQUIRED_FIELDS = [
        'paymentNumberFrom',
        'paymentNumberTo',
        'intervalValue',
        'intervalUnit',
        'coinAmount',
        'currency',
    ];

    /**
     * @param array $rules
     * @param bool $requireAmounts
     * @return array[]
     */
    public function validate(array $rules, bool $requireAmounts = true): array
    {
        if ($rules === []) {
            return [['row' => -1, 'field' => 'rules', 'code' => self::ERR_NO_RULES]];
        }

        $errors = [];
        $rules = array_values($rules);
        $required = $requireAmounts
            ? self::REQUIRED_FIELDS
            : array_values(array_diff(self::REQUIRED_FIELDS, ['coinAmount', 'currency']));

        foreach ($rules as $row => $rule) {
            $rule = is_array($rule) ? $rule : [];

            foreach ($required as $field) {
                if (!array_key_exists($field, $rule) || $rule[$field] === null || trim((string) $rule[$field]) === '') {
                    $errors[] = ['row' => $row, 'field' => $field, 'code' => self::ERR_MISSING];
                }
            }

            foreach (['paymentNumberFrom', 'paymentNumberTo', 'intervalValue'] as $field) {
                if ($this->has($rule, $field)) {
                    if (!$this->isInt($rule[$field])) {
                        $errors[] = ['row' => $row, 'field' => $field, 'code' => self::ERR_NOT_INTEGER];
                    } elseif ((int) $rule[$field] < 1) {
                        $errors[] = ['row' => $row, 'field' => $field, 'code' => self::ERR_NOT_POSITIVE];
                    }
                }
            }

            if ($requireAmounts && $this->has($rule, 'coinAmount')) {
                if (!$this->isInt($rule['coinAmount'])) {
                    $errors[] = ['row' => $row, 'field' => 'coinAmount', 'code' => self::ERR_NOT_INTEGER];
                } elseif ((int) $rule['coinAmount'] < 0) {
                    $errors[] = ['row' => $row, 'field' => 'coinAmount', 'code' => self::ERR_NEGATIVE];
                }
            }

            if ($this->has($rule, 'intervalUnit')
                && !in_array(strtoupper((string) $rule['intervalUnit']), self::INTERVAL_UNITS, true)
            ) {
                $errors[] = ['row' => $row, 'field' => 'intervalUnit', 'code' => self::ERR_BAD_UNIT];
            }

            if ($requireAmounts && $this->has($rule, 'currency')
                && !in_array((string) $rule['currency'], self::supportedCurrencyCodes(), true)
            ) {
                $errors[] = ['row' => $row, 'field' => 'currency', 'code' => self::ERR_BAD_CURRENCY];
            }
        }

        return array_merge($errors, $errors === [] ? $this->validateRanges($rules) : []);
    }

    /**
     * @param array $rules
     * @param bool $withAmounts
     * @return array
     */
    public function normalize(array $rules, bool $withAmounts = true): array
    {
        $normalized = [];

        foreach (array_values($rules) as $rule) {
            $row = [
                'paymentNumberFrom' => (int) $rule['paymentNumberFrom'],
                'paymentNumberTo' => (int) $rule['paymentNumberTo'],
                'intervalValue' => (int) $rule['intervalValue'],
                'intervalUnit' => strtoupper((string) $rule['intervalUnit']),
            ];

            if ($withAmounts) {
                $row['coinAmount'] = (int) $rule['coinAmount'];
                $row['currency'] = (string) $rule['currency'];
            }

            $normalized[] = $row;
        }

        usort($normalized, static fn (array $a, array $b): int => $a['paymentNumberFrom'] <=> $b['paymentNumberFrom']);

        return $normalized;
    }

    /**
     * Subscriptions are UAH-only (Config::SUBSCRIPTION_CURRENCY_CODE) — unrelated to which
     * currencies a one-off payment accepts (Config::CURRENCY_CODE_MAP covers those).
     *
     * @return string[]
     */
    public static function supportedCurrencyCodes(): array
    {
        return [(string) Config::SUBSCRIPTION_CURRENCY_CODE];
    }

    /**
     * @param array $rules
     * @return array
     */
    private function validateRanges(array $rules): array
    {
        $errors = [];
        $sorted = array_values($rules);
        $originalRows = array_keys($sorted);
        array_multisort(
            array_map(static fn (array $rule): int => (int) $rule['paymentNumberFrom'], $sorted),
            SORT_ASC,
            $originalRows,
            $sorted
        );

        $expectedFrom = 1;

        foreach ($sorted as $position => $rule) {
            $row = $originalRows[$position];
            $from = (int) $rule['paymentNumberFrom'];
            $to = (int) $rule['paymentNumberTo'];

            if ($from > $to) {
                $errors[] = ['row' => $row, 'field' => 'paymentNumberTo', 'code' => self::ERR_RANGE_ORDER];
                continue;
            }

            if ($position === 0 && $from !== 1) {
                $errors[] = ['row' => $row, 'field' => 'paymentNumberFrom', 'code' => self::ERR_RANGE_START];
            } elseif ($from !== $expectedFrom) {
                $errors[] = ['row' => $row, 'field' => 'paymentNumberFrom', 'code' => self::ERR_RANGE_GAP_OR_OVERLAP];
            }

            $expectedFrom = $to + 1;
        }

        return $errors;
    }

    private function has(array $rule, string $field): bool
    {
        return array_key_exists($field, $rule) && $rule[$field] !== null && trim((string) $rule[$field]) !== '';
    }

    private function isInt(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1);
    }
}
