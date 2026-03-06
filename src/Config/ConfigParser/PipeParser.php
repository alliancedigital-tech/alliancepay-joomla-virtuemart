<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Config\ConfigParser
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Config\ConfigParser;

class PipeParser
{
    /**
     * @param string $paramsString
     *
     * @return array
     *
     * @since version 1.0.0
     */
    public static function decode(string $paramsString): array
    {
        $params = [];
        $pairs = explode('|', $paramsString);

        foreach ($pairs as $pair) {
            $pair = trim($pair);

            if (empty($pair)) {
                continue;
            }

            $parts = explode('=', $pair, 2);

            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $value = trim($value, '"\'');
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public static function encode(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            $value = addslashes($value);
            $pairs[] = $key . '="' . $value . '"';
        }

        return implode('|', $pairs);
    }
}
