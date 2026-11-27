<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Local status of a fiscalization record: PENDING / SUCCESS / FAIL come from Ecom, ERROR is local
 * (network, 5xx, timeout, bad signature, 400 or local validation - nothing was accepted by Ecom).
 *
 * @since version 1.6.0
 */
enum FiscalizationStatus: string
{
    case Pending = 'PENDING';
    case Success = 'SUCCESS';
    case Fail = 'FAIL';
    case Error = 'ERROR';

    public static function fromApi(?string $status): ?self
    {
        return match (strtoupper(trim((string) $status))) {
            'PENDING' => self::Pending,
            'SUCCESS' => self::Success,
            'FAIL' => self::Fail,
            default => null,
        };
    }

    /** "Fiscalize" button: only when there is no record yet or the last attempt failed locally. */
    public static function canFiscalize(?self $status): bool
    {
        return $status === null || $status === self::Error;
    }

    /** "Refresh status" button: waiting for the callback, or checking a FAIL. */
    public static function canRefresh(?self $status): bool
    {
        return $status === self::Pending || $status === self::Fail;
    }
}
