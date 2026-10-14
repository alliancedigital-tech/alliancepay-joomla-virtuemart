<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use RuntimeException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class PlanValidationException extends RuntimeException
{
    /**
     * @param array<int, array{row: int, field: string, code: string}> $errors
     */
    public function __construct(private readonly array $errors, string $message = "Plan validation failed")
    {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{row: int, field: string, code: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
