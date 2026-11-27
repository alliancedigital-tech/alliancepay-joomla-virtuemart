<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

use RuntimeException;

/**
 * Thrown when a fiscalization request must not be sent because local validation failed.
 *
 * @since version 1.6.0
 */
final class FiscalizationValidationException extends RuntimeException
{
    /**
     * @param FiscalizationIssue[] $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(
            'Fiscalization validation failed: ' . implode('; ', array_map(
                static fn (FiscalizationIssue $issue): string => $issue->key . ($issue->params ? ' (' . implode(', ', $issue->params) . ')' : ''),
                $issues
            ))
        );
    }

    public static function single(string $key, array $params = []): self
    {
        return new self([new FiscalizationIssue($key, $params)]);
    }
}
