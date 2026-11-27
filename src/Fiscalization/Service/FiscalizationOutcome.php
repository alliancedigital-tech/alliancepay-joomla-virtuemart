<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStatus;

/**
 * Result of a fiscalize / refresh action, ready to show to the administrator.
 *
 * @since version 1.6.0
 */
final class FiscalizationOutcome
{
    /**
     * @param string[] $messages translated reasons when the action did not succeed
     * @param array|null $record the fiscalization row after the action
     */
    public function __construct(
        public readonly bool $accepted,
        public readonly ?FiscalizationStatus $status,
        public readonly array $messages = [],
        public readonly ?array $record = null
    ) {
    }

    public function message(): string
    {
        return implode(' ', $this->messages);
    }
}
