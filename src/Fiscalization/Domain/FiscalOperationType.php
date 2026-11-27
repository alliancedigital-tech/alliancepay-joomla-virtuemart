<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * @since version 1.6.0
 */
enum FiscalOperationType: string
{
    case Purchase = 'PURCHASE';
    case Refund = 'REFUND';
}
