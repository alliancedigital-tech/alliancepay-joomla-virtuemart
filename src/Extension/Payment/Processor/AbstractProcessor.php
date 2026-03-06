<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\Processor;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\Payment\Processor
 *
 * @since version 1.0.0
 */
abstract class AbstractProcessor
{
    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function generateMerchantRequestId(): string
    {
        return uniqid();
    }

    /**
     * @param float $amount
     *
     * @return int
     *
     * @since version 1.0.0
     */
    public function prepareCoinAmount(float $amount)
    {
        if (!empty($amount)) {
            $amount = $amount * 100;
        }

        return (int) $amount;
    }
}
