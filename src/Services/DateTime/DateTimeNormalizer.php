<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime;

use Joomla\CMS\Factory;
use Exception;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime
 *
 * @since version 1.0.0
 */
class DateTimeNormalizer
{
    public const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    public const REFUND_DATE_FORMAT = 'Y-m-d H:i:s.vP';

    /**
     * @param string $inputDate
     *
     * @return string
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function normalizeDate(string $inputDate): string
    {
        $config = Factory::getApplication()->getConfig();
        $timestamp = strtotime($inputDate);
        $timeZone = $config->get('offset');
        $date = Factory::getDate($timestamp, $timeZone);

        return $date->format(self::DATE_TIME_FORMAT);
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getRefundDate(): string
    {
        $date = Factory::getDate('now');

        return preg_replace(
            '/(\.\d{2})\d/',
            '$1',
            $date->format(self::REFUND_DATE_FORMAT)
        );
    }
}
