<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Support;

/**
 * Reads VirtueMart's "key=<json>|key2=<json>" parameter strings (customfield_params, payment_params).
 *
 * VirtueMart stores every value JSON-encoded, so a string comes back as "text" with escapes such as
 * \/, \n and \uXXXX (Cyrillic) that a plain split would leave in place. Pure PHP.
 *
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Support
 *
 * @since version 1.6.0
 */
final class VmParams
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed> decoded values by key (a value that is not valid JSON is kept as is)
     */
    public static function parse(?string $params): array
    {
        $result = [];

        foreach (explode('|', (string) $params) as $pair) {
            $pair = trim($pair);

            if ($pair === '' || $pair === 'NULL' || !str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $decoded = json_decode(trim($value), true);
            $result[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : trim($value, " \t\"'");
        }

        return $result;
    }

    /**
     * Decodes a single string value whose surrounding quotes were already removed (as PipeParser does):
     * turns \/, \n, \uXXXX into the real characters. A value without a backslash is returned unchanged.
     */
    public static function unescape(string $value): string
    {
        if (!str_contains($value, '\\')) {
            return $value;
        }

        $decoded = json_decode('"' . str_replace('"', '\\"', $value) . '"');

        return is_string($decoded) ? $decoded : $value;
    }
}
