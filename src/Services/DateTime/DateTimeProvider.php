<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime;

use Exception;
use Joomla\CMS\Factory;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime
 *
 * @since version 1.0.0
 */
class DateTimeProvider
{
    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getNowDate(): string
    {
        $date = Factory::getDate();

        return $date->toSql();
    }
}
