<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanRulesBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanTemplate;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\RuleValidator;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\TemplateStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\TemplateRepository;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class TemplateService
{
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly PlanRepository $planRepository,
        private readonly AuditLogRepository $auditLog,
        private readonly RuleValidator $ruleValidator,
        private readonly PlanRulesBuilder $rulesBuilder
    ) {
    }

    /**
     * @param string $code
     * @param string $name
     * @param string $categoryIndicator
     * @param array $rulesTemplate
     * @return PlanTemplate
     */
    public function create(string $code, string $name, string $categoryIndicator, array $rulesTemplate): PlanTemplate
    {
        $code = trim($code);
        $errors = $this->validate($code, $name, $rulesTemplate);

        if ($errors !== []) {
            throw new PlanValidationException($errors);
        }

        try {
            $id = $this->templateRepository->insert([
                'code' => $code,
                'name' => trim($name),
                'category_indicator' => trim($categoryIndicator),
                'rules_template' => json_encode($this->normalizeStructure($rulesTemplate), JSON_UNESCAPED_UNICODE),
                'status' => TemplateStatus::Active->value,
            ]);
        } catch (DuplicateKeyException $e) {
            throw new PlanValidationException([['row' => -1, 'field' => 'code', 'code' => 'duplicate']]);
        }

        $this->auditLog->log(AuditLogRepository::ACTION_TEMPLATE_CREATED, null, null, ['template_id' => $id, 'code' => $code]);

        return $this->templateRepository->find($id);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $categoryIndicator
     * @param array $rulesTemplate
     * @return PlanTemplate
     */
    public function update(int $id, string $name, string $categoryIndicator, array $rulesTemplate): PlanTemplate
    {
        if ($this->planRepository->isTemplateUsed($id)) {
            throw new TemplateInUseException('The template is used by a plan; archive it instead of editing');
        }

        $template = $this->templateRepository->find($id);
        $errors = $this->validate($template?->code ?? '', $name, $rulesTemplate);

        if ($template === null || $errors !== []) {
            throw new PlanValidationException($errors ?: [['row' => -1, 'field' => 'id', 'code' => RuleValidator::ERR_MISSING]]);
        }

        $this->templateRepository->update($id, [
            'name' => trim($name),
            'category_indicator' => trim($categoryIndicator),
            'rules_template' => json_encode($this->normalizeStructure($rulesTemplate), JSON_UNESCAPED_UNICODE),
        ]);

        return $this->templateRepository->find($id);
    }

    public function archive(int $id): void
    {
        $this->templateRepository->update($id, ['status' => TemplateStatus::Archived->value]);
        $this->auditLog->log(AuditLogRepository::ACTION_TEMPLATE_ARCHIVED, null, null, ['template_id' => $id]);
    }

    /**
     * @param PlanTemplate $template
     * @param int $coinAmount
     * @param string $currency
     * @return array
     */
    public function buildRules(PlanTemplate $template, int $coinAmount, string $currency): array
    {
        return $this->rulesBuilder->build($template->rulesTemplate, $coinAmount, $currency);
    }

    private function validate(string $code, string $name, array $rulesTemplate): array
    {
        $errors = $this->ruleValidator->validate($rulesTemplate, false);

        if ($code === '') {
            $errors[] = ['row' => -1, 'field' => 'code', 'code' => RuleValidator::ERR_MISSING];
        }

        if (trim($name) === '') {
            $errors[] = ['row' => -1, 'field' => 'name', 'code' => RuleValidator::ERR_MISSING];
        }

        return $errors;
    }

    /**
     * @param array $rulesTemplate
     * @return array
     */
    private function normalizeStructure(array $rulesTemplate): array
    {
        $normalized = $this->ruleValidator->normalize($rulesTemplate, false);
        $byFrom = [];

        foreach (array_values($rulesTemplate) as $row) {
            $byFrom[(int) $row['paymentNumberFrom']] = ($row['stage'] ?? PlanRulesBuilder::STAGE_PAID) === PlanRulesBuilder::STAGE_TRIAL
                ? PlanRulesBuilder::STAGE_TRIAL
                : PlanRulesBuilder::STAGE_PAID;
        }

        foreach ($normalized as &$row) {
            $row['stage'] = $byFrom[$row['paymentNumberFrom']] ?? PlanRulesBuilder::STAGE_PAID;
        }

        return $normalized;
    }
}
