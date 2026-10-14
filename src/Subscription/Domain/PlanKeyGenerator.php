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
class PlanKeyGenerator
{
    public function __construct(private readonly RuleValidator $ruleValidator)
    {
    }

    /**
     * @param string $merchantId
     * @param int $productId
     * @param int|null $templateId
     * @param string $categoryIndicator
     * @param array $rules valid rules (see RuleValidator)
     *
     * @return string 64-char hex sha256
     */
    public function generate(
        string $merchantId,
        int $productId,
        ?int $templateId,
        string $categoryIndicator,
        array $rules
    ): string {
        $normalizedRules = $this->ruleValidator->normalize($rules);

        return hash(
            'sha256',
            implode('|', [
                trim($merchantId),
                $productId,
                (int) $templateId,
                trim($categoryIndicator),
                json_encode($normalizedRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])
        );
    }
}
