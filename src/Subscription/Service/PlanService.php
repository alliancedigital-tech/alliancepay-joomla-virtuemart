<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Plan;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanKeyGenerator;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\RuleValidator;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SyncStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\VersionHistoryRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class PlanService
{
    private const LOG_CATEGORY = 'alliance_payment';
    private const LOCK_PREFIX = 'alliancepay_plan_';
    public const ERR_NOT_SIMPLE_PRODUCT = 'not_simple_product';

    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly PlanRepository $planRepository,
        private readonly AuditLogRepository $auditLog,
        private readonly VersionHistoryRepository $versionHistory,
        private readonly RuleValidator $ruleValidator,
        private readonly PlanKeyGenerator $planKeyGenerator,
        private readonly Config $config,
        private readonly DatabaseInterface $db
    ) {
    }

    /**
     * @param int $productId
     * @param string $name
     * @param string|null $description
     * @param string $categoryIndicator
     * @param array $rules
     * @param int|null $templateId
     * @param string|null $appliesFrom
     * @param string|null $appliesTo
     * @return Plan
     */
    public function createPlan(
        int $productId,
        string $name,
        ?string $description,
        string $categoryIndicator,
        array $rules,
        ?int $templateId = null,
        ?string $appliesFrom = null,
        ?string $appliesTo = null
    ): Plan {
        $errors = $this->ruleValidator->validate($rules);

        if ($errors !== []) {
            throw new PlanValidationException($errors);
        }

        if ($productId < 1 || trim($name) === '') {
            throw new PlanValidationException([
                ['row' => -1, 'field' => $productId < 1 ? 'productId' : 'name', 'code' => RuleValidator::ERR_MISSING],
            ]);
        }

        if (!$this->planRepository->isSimpleProduct($productId)) {
            throw new PlanValidationException([
                ['row' => -1, 'field' => 'productId', 'code' => self::ERR_NOT_SIMPLE_PRODUCT],
            ]);
        }

        $rules = $this->ruleValidator->normalize($rules);
        $merchantId = $this->config->getMerchantId();
        $planKey = $this->planKeyGenerator->generate($merchantId, $productId, $templateId, $categoryIndicator, $rules);

        $lockName = self::LOCK_PREFIX . substr($planKey, 0, 32);

        if (!$this->acquireLock($lockName)) {
            throw new PlanAlreadyExistsException(null, 'The same plan is being created by another request');
        }

        try {
            $existing = $this->planRepository->findByPlanKey($planKey);

            if ($existing !== null) {
                throw new PlanAlreadyExistsException($existing);
            }

            $request = [
                'name' => $name,
                'categorySubcategoryIndicator' => $categoryIndicator,
                'merchantId' => $merchantId,
                'rules' => $rules,
            ];

            if ($description !== null && $description !== '') {
                $request['description'] = $description;
            }

            if ($appliesFrom !== null) {
                $request['appliesDateTimeFrom'] = $appliesFrom;
            }

            if ($appliesTo !== null) {
                $request['appliesDateTimeTo'] = $appliesTo;
            }

            $response = $this->apiClient->createPlan($request);
            $providerPlanId = (string) ($response['planId'] ?? '');
            $versionId = (string) ($response['versionId'] ?? '');

            if ($providerPlanId === '' || $versionId === '') {
                throw new ProviderApiException(
                    'AlliancePay: plan create response has no planId / versionId',
                    200,
                    null,
                    [],
                    (string) json_encode($response)
                );
            }

            $planId = $this->storePlan([
                'provider_plan_id' => $providerPlanId,
                'product_id' => $productId,
                'template_id' => $templateId,
                'plan_key' => $planKey,
                'is_active' => 1,
                'active_subscriptions_count' => 0,
                'sync_status' => SyncStatus::Error->value,
                'sync_error' => 'Infinite schedule not applied yet',
                'version_id' => $versionId,
                'merchant_id' => $merchantId,
                'name' => $name,
                'description' => $description,
                'status' => PlanStatus::Error->value,
                'category_indicator' => $categoryIndicator,
                'rules' => json_encode($this->rulesFromResponse($response, $rules), JSON_UNESCAPED_UNICODE),
                'applies_from' => $appliesFrom,
                'applies_to' => $appliesTo,
            ]);

            try {
                $this->apiClient->setInfiniteVersion($providerPlanId, $versionId);
            } catch (ProviderApiException $e) {
                $this->planRepository->update($planId, ['sync_error' => $e->getAdminMessage()]);
                $plan = $this->planRepository->find($planId);
                $this->auditLog->log(
                    AuditLogRepository::ACTION_PLAN_SYNC_FAILED,
                    $planId,
                    $productId,
                    ['step' => 'infinite_version', 'error' => $e->getAdminMessage(), 'provider_plan_id' => $providerPlanId]
                );

                throw new PlanSyncException(
                    $plan,
                    'The plan was created at the provider but its infinite schedule could not be set: ' . $e->getAdminMessage(),
                    $e
                );
            }

            $this->planRepository->update(
                $planId,
                ['status' => $this->mapProviderStatus($response['status'] ?? null)->value, 'sync_status' => SyncStatus::Synced->value],
                []
            );
            $this->clearSyncError($planId);

            $this->auditLog->log(
                AuditLogRepository::ACTION_PLAN_CREATED,
                $planId,
                $productId,
                ['provider_plan_id' => $providerPlanId, 'version_id' => $versionId, 'template_id' => $templateId]
            );

            return $this->planRepository->find($planId);
        } finally {
            $this->releaseLock($lockName);
        }
    }

    /**
     * @param int $planId
     * @return Plan
     */
    public function retrySync(int $planId): Plan
    {
        $plan = $this->planRepository->find($planId);

        if ($plan === null) {
            throw new PlanValidationException([['row' => -1, 'field' => 'planId', 'code' => RuleValidator::ERR_MISSING]]);
        }

        if ($plan->syncStatus === SyncStatus::Synced) {
            return $plan;
        }

        if ($plan->versionId === null || $plan->versionId === '') {
            throw new ProviderApiException('AlliancePay: the plan has no version id; it cannot be completed automatically');
        }

        try {
            $this->apiClient->setInfiniteVersion($plan->providerPlanId, $plan->versionId);
        } catch (ProviderApiException $e) {
            $this->planRepository->update($planId, ['sync_error' => $e->getAdminMessage()]);
            $this->auditLog->log(
                AuditLogRepository::ACTION_PLAN_SYNC_FAILED,
                $planId,
                $plan->productId,
                ['step' => 'infinite_version_retry', 'error' => $e->getAdminMessage()]
            );

            throw $e;
        }

        $this->planRepository->update($planId, [
            'status' => PlanStatus::Active->value,
            'sync_status' => SyncStatus::Synced->value,
        ]);
        $this->clearSyncError($planId);
        $this->auditLog->log(AuditLogRepository::ACTION_PLAN_SYNC_RETRIED, $planId, $plan->productId);

        return $this->planRepository->find($planId);
    }

    /**
     * @param int $planId
     * @param bool $offered
     * @return void
     */
    public function setOfferedToNewCustomers(int $planId, bool $offered): void
    {
        $plan = $this->planRepository->find($planId);

        if ($plan === null) {
            return;
        }

        $this->planRepository->update($planId, ['is_active' => $offered ? 1 : 0]);
        $this->auditLog->log(
            $offered ? AuditLogRepository::ACTION_PLAN_REPLACED : AuditLogRepository::ACTION_PLAN_DEACTIVATED,
            $planId,
            $plan->productId,
            ['offered_to_new_customers' => $offered]
        );
    }

    /**
     * @param int $planId
     * @param array $newRules
     * @param string $appliesFrom
     * @param bool $customersNotified
     * @param string $reason
     * @param int|null $userId
     * @return Plan
     */
    public function reprice(
        int $planId,
        array $newRules,
        string $appliesFrom,
        bool $customersNotified,
        string $reason,
        ?int $userId = null
    ): Plan {
        $plan = $this->planRepository->find($planId);

        if ($plan === null || $plan->status !== PlanStatus::Active || $plan->syncStatus !== SyncStatus::Synced) {
            throw new PlanValidationException([['row' => -1, 'field' => 'planId', 'code' => 'plan_not_eligible']]);
        }

        $errors = $this->ruleValidator->validate($newRules);

        if (!$customersNotified) {
            $errors[] = ['row' => -1, 'field' => 'customersNotified', 'code' => 'confirmation_required'];
        }

        $timestamp = strtotime($appliesFrom . ' UTC');
        $earliest = time() + $this->config->getSubscriptionRepriceMinDays() * 86400;

        if ($timestamp === false) {
            $errors[] = ['row' => -1, 'field' => 'appliesFrom', 'code' => 'bad_date'];
        } elseif ($timestamp < $earliest) {
            $errors[] = ['row' => -1, 'field' => 'appliesFrom', 'code' => 'too_early'];
        }

        if ($errors !== []) {
            throw new PlanValidationException($errors);
        }

        $newRules = $this->ruleValidator->normalize($newRules);
        $newKey = $this->planKeyGenerator->generate(
            $plan->merchantId,
            $plan->productId,
            $plan->templateId,
            $plan->categoryIndicator,
            $newRules
        );
        $clash = $this->planRepository->findByPlanKey($newKey);

        if ($clash !== null && $clash->id !== $plan->id) {
            throw new PlanAlreadyExistsException($clash, 'Another plan with exactly these rules already exists');
        }

        $response = $this->apiClient->updatePlan([
            'planId' => $plan->providerPlanId,
            'name' => $plan->name,
            'categorySubcategoryIndicator' => $plan->categoryIndicator,
            'merchantId' => $plan->merchantId,
            'appliesDateTimeFrom' => $appliesFrom,
            'rules' => $newRules,
        ]);

        $newVersionId = (string) ($response['versionId'] ?? '');

        if ($newVersionId === '') {
            throw new ProviderApiException(
                'AlliancePay: plan update response has no versionId',
                200,
                null,
                [],
                (string) json_encode($response)
            );
        }

        $storedRules = $this->rulesFromResponse($response, $newRules);
        $userId ??= $this->currentUserId();

        $this->planRepository->update($plan->id, [
            'rules' => json_encode($storedRules, JSON_UNESCAPED_UNICODE),
            'version_id' => $newVersionId,
            'applies_from' => $appliesFrom,
            'plan_key' => $newKey,
        ]);

        $this->versionHistory->insert([
            'plan_id' => $plan->id,
            'old_version_id' => $plan->versionId,
            'new_version_id' => $newVersionId,
            'old_rules' => json_encode($plan->rules, JSON_UNESCAPED_UNICODE),
            'new_rules' => json_encode($storedRules, JSON_UNESCAPED_UNICODE),
            'applies_from' => $appliesFrom,
            'reason' => $reason,
            'created_by' => $userId,
        ]);

        $this->auditLog->log(
            AuditLogRepository::ACTION_PLAN_REPRICED,
            $plan->id,
            $plan->productId,
            [
                'old_version_id' => $plan->versionId,
                'new_version_id' => $newVersionId,
                'applies_from' => $appliesFrom,
                'affected_subscriptions' => $plan->activeSubscriptionsCount,
                'reason' => $reason,
            ],
            $userId
        );

        try {
            $this->apiClient->setInfiniteVersion($plan->providerPlanId, $newVersionId);
        } catch (ProviderApiException $e) {
            $this->planRepository->update($plan->id, [
                'status' => PlanStatus::Error->value,
                'sync_status' => SyncStatus::Error->value,
                'sync_error' => $e->getAdminMessage(),
            ]);
            $this->auditLog->log(
                AuditLogRepository::ACTION_PLAN_SYNC_FAILED,
                $plan->id,
                $plan->productId,
                ['step' => 'infinite_version_after_reprice', 'error' => $e->getAdminMessage()],
                $userId
            );

            throw new PlanSyncException(
                $this->planRepository->find($plan->id),
                'The new version was created but its infinite schedule could not be set: ' . $e->getAdminMessage(),
                $e
            );
        }

        return $this->planRepository->find($plan->id);
    }

    /**
     * @param array $response
     * @param array $sentRules
     * @return array
     */
    private function rulesFromResponse(array $response, array $sentRules): array
    {
        $providerRules = $response['rules'] ?? null;

        if (is_array($providerRules) && $providerRules !== [] && $this->ruleValidator->validate($providerRules) === []) {
            return $this->ruleValidator->normalize($providerRules);
        }

        return $sentRules;
    }

    private function mapProviderStatus(mixed $status): PlanStatus
    {
        return PlanStatus::tryFrom(strtoupper((string) $status)) ?? PlanStatus::Active;
    }

    /**
     * @throws PlanAlreadyExistsException on a race lost against the UNIQUE index
     */
    private function storePlan(array $row): int
    {
        try {
            return $this->planRepository->insert($row);
        } catch (DuplicateKeyException $e) {
            Log::add(
                'Plan insert rejected by a unique index after the provider call (provider_plan_id='
                . ($row['provider_plan_id'] ?? '') . '): ' . $e->getMessage(),
                Log::ERROR,
                self::LOG_CATEGORY
            );

            throw new PlanAlreadyExistsException(
                $this->planRepository->findByPlanKey((string) ($row['plan_key'] ?? ''))
                ?? $this->planRepository->findByProviderPlanId((string) ($row['provider_plan_id'] ?? ''))
            );
        }
    }

    /**
     * @param int $planId
     * @return void
     */
    private function clearSyncError(int $planId): void
    {
        $this->planRepository->update($planId, ['sync_error' => null], ['sync_error']);
    }

    /**
     * @param string $name
     * @return bool
     */
    private function acquireLock(string $name): bool
    {
        try {
            $this->db->setQuery('SELECT GET_LOCK(' . $this->db->quote($name) . ', 0)');

            return (int) $this->db->loadResult() === 1;
        } catch (Throwable $e) {
            Log::add('Plan lock failed: ' . $e->getMessage(), Log::WARNING, self::LOG_CATEGORY);

            return true;
        }
    }

    /**
     * @param string $name
     * @return void
     */
    private function releaseLock(string $name): void
    {
        try {
            $this->db->setQuery('SELECT RELEASE_LOCK(' . $this->db->quote($name) . ')');
            $this->db->loadResult();
        } catch (Throwable $e) {
            Log::add('Plan unlock failed: ' . $e->getMessage(), Log::WARNING, self::LOG_CATEGORY);
        }
    }

    /**
     * @return int
     */
    private function currentUserId(): int
    {
        try {
            return (int) Factory::getApplication()->getIdentity()->id;
        } catch (Throwable) {
            return 0;
        }
    }
}
