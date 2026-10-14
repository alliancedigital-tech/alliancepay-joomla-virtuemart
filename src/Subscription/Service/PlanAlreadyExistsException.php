<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Plan;
use RuntimeException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class PlanAlreadyExistsException extends RuntimeException
{
    public function __construct(private readonly ?Plan $existing, string $message = "Such a plan already exists")
    {
        parent::__construct($message);
    }

    /**
     * @return Plan|null
     */
    public function getExistingPlan(): ?Plan
    {
        return $this->existing;
    }
}
