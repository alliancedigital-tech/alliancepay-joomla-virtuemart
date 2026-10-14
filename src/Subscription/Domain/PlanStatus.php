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
enum PlanStatus: string
{
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Canceled = 'CANCELED';
    case Error = 'ERROR';
}
