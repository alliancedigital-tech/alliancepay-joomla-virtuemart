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
final class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly int $virtuemartOrderId,
        public readonly int $virtuemartUserId,
        public readonly int $planId,
        public readonly array $rulesSnapshot,
        public readonly ?string $providerOrderId,
        public readonly string $merchantRequestId,
        public readonly SubscriptionStatus $status,
        public readonly ?array $callbackData,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    public static function fromRow(array $row): self
    {
        $snapshot = json_decode((string) ($row['rules_snapshot'] ?? '[]'), true);
        $callback = json_decode((string) ($row['callback_data'] ?? ''), true);

        return new self(
            (int) $row['id'],
            (int) $row['virtuemart_order_id'],
            (int) $row['virtuemart_user_id'],
            (int) $row['plan_id'],
            is_array($snapshot) ? $snapshot : [],
            $row['provider_order_id'] !== null && $row['provider_order_id'] !== '' ? (string) $row['provider_order_id'] : null,
            (string) $row['merchant_request_id'],
            SubscriptionStatus::tryFrom((string) $row['status']) ?? SubscriptionStatus::Failed,
            is_array($callback) ? $callback : null,
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }

    /**
     * @param int $paymentNumber
     * @return int|null
     */
    public function getExpectedCoinAmount(int $paymentNumber): ?int
    {
        foreach ($this->rulesSnapshot as $rule) {
            if ($paymentNumber >= (int) ($rule['paymentNumberFrom'] ?? 0)
                && $paymentNumber <= (int) ($rule['paymentNumberTo'] ?? 0)
            ) {
                return (int) ($rule['coinAmount'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @param int $openEndedThreshold
     * @return int|null
     */
    public function getLastPaymentNumber(int $openEndedThreshold = 999): ?int
    {
        $last = 0;

        foreach ($this->rulesSnapshot as $rule) {
            $last = max($last, (int) ($rule['paymentNumberTo'] ?? 0));
        }

        return $last > 0 && $last < $openEndedThreshold ? $last : null;
    }
}
