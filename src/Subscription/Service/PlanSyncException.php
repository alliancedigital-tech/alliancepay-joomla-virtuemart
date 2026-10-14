<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Plan;
use RuntimeException;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class PlanSyncException extends RuntimeException
{
    public function __construct(private readonly Plan $plan, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }
}
