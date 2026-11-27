<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Decides whether incoming data (a synchronous response or a callback) may overwrite the stored record.
 * Rules: a SUCCESS is never replaced by PENDING or FAIL; data older than what is stored (by
 * modificationDateTime) is ignored; when a date is missing, the update is applied only if it does not
 * lower the status (PENDING -> SUCCESS / FAIL). Pure PHP.
 *
 * @since version 1.6.0
 */
final class FiscalizationStateMachine
{
    private const RANK = ['ERROR' => 0, 'PENDING' => 1, 'FAIL' => 2, 'SUCCESS' => 2];

    /**
     * @param string|null $storedModified UTC 'Y-m-d H:i:s' of the stored data, null if unknown
     * @param string|null $incomingModified UTC 'Y-m-d H:i:s' of the incoming data, null if unknown
     */
    public static function shouldApply(
        ?FiscalizationStatus $stored,
        FiscalizationStatus $incoming,
        ?string $storedModified,
        ?string $incomingModified
    ): bool {
        if ($stored === null || $stored === FiscalizationStatus::Error) {
            return true;
        }

        if ($stored === FiscalizationStatus::Success && $incoming !== FiscalizationStatus::Success) {
            return false;
        }

        if ($storedModified !== null && $incomingModified !== null) {
            return $incomingModified >= $storedModified;
        }

        return self::RANK[$incoming->value] >= self::RANK[$stored->value];
    }
}
