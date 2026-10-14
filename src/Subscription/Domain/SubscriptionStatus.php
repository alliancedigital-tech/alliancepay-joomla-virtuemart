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
enum SubscriptionStatus: string
{
    case Pending = 'PENDING';
    case Active = 'ACTIVE';
    case Failed = 'FAILED';
    case Canceled = 'CANCELED';
    case Completed = 'COMPLETED';

    /**
     * @return bool
     */
    public function countsAsActive(): bool
    {
        return $this === self::Active;
    }
}
