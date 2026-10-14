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
final class PlanTemplate
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $categoryIndicator,
        public readonly array $rulesTemplate,
        public readonly TemplateStatus $status,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    public static function fromRow(array $row): self
    {
        $rules = json_decode((string) ($row['rules_template'] ?? '[]'), true);

        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['category_indicator'],
            is_array($rules) ? $rules : [],
            TemplateStatus::tryFrom((string) $row['status']) ?? TemplateStatus::Archived,
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }
}
