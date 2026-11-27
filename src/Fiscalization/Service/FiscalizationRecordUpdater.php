<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;

/**
 * Applies a response / callback to a stored fiscalization record.
 *
 * @since version 1.6.0
 */
interface FiscalizationRecordUpdater
{
    /**
     * @param array $record the stored row
     * @param array $payload the raw body
     * @param string $source 'response' or 'callback'
     *
     * @return array the row after the update
     */
    public function applyParsed(array $record, ParsedFiscalization $parsed, array $payload, string $source): array;
}
