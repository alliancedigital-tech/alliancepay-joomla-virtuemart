<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Http;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue;

/**
 * Outcome of the fiscalization request: either the response body, or the reason it failed.
 *
 * @since version 1.6.0
 */
final class FiscalizationApiResult
{
    private function __construct(
        public readonly ?array $body,
        public readonly ?FiscalizationIssue $issue,
        public readonly int $httpStatus
    ) {
    }

    public static function success(array $body, int $httpStatus): self
    {
        return new self($body, null, $httpStatus);
    }

    public static function failure(FiscalizationIssue $issue, int $httpStatus = 0): self
    {
        return new self(null, $issue, $httpStatus);
    }

    public function isSuccess(): bool
    {
        return $this->body !== null;
    }
}
