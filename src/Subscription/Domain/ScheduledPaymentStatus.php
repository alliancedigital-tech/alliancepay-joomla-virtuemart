<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain
 *
 * @since version 1.5.0
 */
enum ScheduledPaymentStatus: string
{
    case Waiting = 'WAITING';
    case Processing = 'PROCESSING';
    case Success = 'SUCCESS';
    case Fail = 'FAIL';
    case Retry = 'RETRY';
    case SkippedPaused = 'SKIPPED_PAUSED';
    case Invalidated = 'INVALIDATED';

    /**
     * @param string $status
     * @return self|null
     */
    public static function fromProvider(string $status): ?self
    {
        return match (strtoupper(trim($status))) {
            'SUCCESS' => self::Success,
            'FAIL', 'FAILED' => self::Fail,
            'PENDING', 'PROCESSING', 'IN_PROGRESS' => self::Processing,
            'RETRY' => self::Retry,
            'SKIPPED_PAUSED', 'SKIPPED' => self::SkippedPaused,
            'INVALIDATED' => self::Invalidated,
            'WAITING', 'SCHEDULED' => self::Waiting,
            default => null,
        };
    }

    /**
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Success, self::Invalidated], true);
    }
}
