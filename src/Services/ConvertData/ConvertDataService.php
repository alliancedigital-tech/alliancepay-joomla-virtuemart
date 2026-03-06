<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData
 *
 * @since version 1.0.0
 */
class ConvertDataService
{
    /**
     * @param array $data
     *
     * @return array
     *
     * @since version 1.0.0
     */
    public function camelToSnakeArrayKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (!is_numeric($key)) {
                $snakeKey = strtolower(
                    preg_replace(
                        '/([a-z])([A-Z])/',
                        '$1_$2',
                        $key
                    )
                );
            }

            $result[$snakeKey] = $value;
        }

        return $result;
    }
}
