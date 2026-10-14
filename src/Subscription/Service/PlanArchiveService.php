<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use RuntimeException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class PlanArchiveService
{
    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly PlanRepository $planRepository,
        private readonly AuditLogRepository $auditLog
    ) {
    }

    /**
     * @return int
     */
    public function markCandidates(): int
    {
        $marked = 0;

        foreach ($this->planRepository->findArchiveCandidates() as $plan) {
            if ($this->planRepository->recomputeActiveCount($plan->id) !== 0) {
                continue;
            }

            $this->planRepository->update($plan->id, ['archive_candidate' => 1]);
            $this->auditLog->log(AuditLogRepository::ACTION_PLAN_ARCHIVE_CANDIDATE, $plan->id, $plan->productId, [], 0);
            $marked++;
        }

        return $marked;
    }

    /**
     * @param int $planId
     * @param int|null $userId
     * @return void
     */
    public function confirmArchive(int $planId, ?int $userId = null): void
    {
        $plan = $this->planRepository->find($planId);

        if ($plan === null || $plan->isActive || !$plan->archiveCandidate) {
            throw new RuntimeException('The plan is not an archive candidate');
        }

        if ($this->planRepository->recomputeActiveCount($planId) !== 0) {
            throw new RuntimeException('The plan still has active subscriptions');
        }

        $this->apiClient->updatePlanStatus([
            'planId' => $plan->providerPlanId,
            'status' => PlanStatus::Canceled->value,
        ]);

        $this->planRepository->update($planId, ['status' => PlanStatus::Canceled->value, 'archive_candidate' => 0]);
        $this->auditLog->log(AuditLogRepository::ACTION_PLAN_ARCHIVED, $planId, $plan->productId, [], $userId);
    }
}
