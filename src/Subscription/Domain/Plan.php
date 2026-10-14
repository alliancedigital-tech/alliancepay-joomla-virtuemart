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
final class Plan
{
    public function __construct(
        public readonly int $id,
        public readonly string $providerPlanId,
        public readonly int $productId,
        public readonly ?int $templateId,
        public readonly string $planKey,
        public readonly bool $isActive,
        public readonly int $activeSubscriptionsCount,
        public readonly SyncStatus $syncStatus,
        public readonly ?string $syncError,
        public readonly bool $archiveCandidate,
        public readonly ?string $versionId,
        public readonly string $merchantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly PlanStatus $status,
        public readonly string $categoryIndicator,
        public readonly array $rules,
        public readonly ?string $appliesFrom,
        public readonly ?string $appliesTo,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    /**
     * @param array $row
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $rules = json_decode((string) ($row['rules'] ?? '[]'), true);

        return new self(
            (int) $row['id'],
            (string) $row['provider_plan_id'],
            (int) $row['product_id'],
            isset($row['template_id']) ? (int) $row['template_id'] : null,
            (string) $row['plan_key'],
            (int) $row['is_active'] === 1,
            (int) $row['active_subscriptions_count'],
            SyncStatus::tryFrom((string) $row['sync_status']) ?? SyncStatus::Error,
            $row['sync_error'] !== null ? (string) $row['sync_error'] : null,
            (int) ($row['archive_candidate'] ?? 0) === 1,
            $row['version_id'] !== null ? (string) $row['version_id'] : null,
            (string) $row['merchant_id'],
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            PlanStatus::tryFrom((string) $row['status']) ?? PlanStatus::Error,
            (string) $row['category_indicator'],
            is_array($rules) ? $rules : [],
            $row['applies_from'] !== null ? (string) $row['applies_from'] : null,
            $row['applies_to'] !== null ? (string) $row['applies_to'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }

    /**
     * @return bool
     */
    public function isOfferable(): bool
    {
        return $this->isActive
            && $this->status === PlanStatus::Active
            && $this->syncStatus === SyncStatus::Synced;
    }

    /**
     * @return int
     */
    public function getFirstPaymentCoinAmount(): int
    {
        foreach ($this->rules as $rule) {
            if ((int) ($rule['paymentNumberFrom'] ?? 0) === 1) {
                return (int) ($rule['coinAmount'] ?? 0);
            }
        }

        return 0;
    }
}
