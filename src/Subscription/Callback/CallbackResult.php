<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback
 *
 * @since version 1.5.0
 */
final class CallbackResult
{
    public const ORDER_STATUS_NONE = null;
    public const ORDER_STATUS_SUCCESS = 'success';
    public const ORDER_STATUS_FAIL = 'fail';

    private function __construct(
        public readonly bool $handled,
        public readonly ?Subscription $subscription = null,
        public readonly ?string $vmOrderStatus = self::ORDER_STATUS_NONE
    ) {
    }

    /**
     * @return self
     */
    public static function notSubscription(): self
    {
        return new self(false);
    }

    /**
     * @param Subscription $subscription
     * @param string|null $vmOrderStatus
     * @return self
     */
    public static function handled(Subscription $subscription, ?string $vmOrderStatus = self::ORDER_STATUS_NONE): self
    {
        return new self(true, $subscription, $vmOrderStatus);
    }
}
