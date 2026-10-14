<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Plan;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\RulesInput;
use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanFormatter;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\TemplateRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\VersionHistoryRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * One plan (detail + reprice + history), or the creation form when layout=create.
 *
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public $plan = null;
    public array $history = [];
    public array $templates = [];
    public array $formRows = [];
    public array $form = [];
    public bool $canReprice = false;
    public bool $canArchive = false;
    public int $repriceMinDays = 30;
    public ?PlanFormatter $formatter = null;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $this->formatter = Services::get(PlanFormatter::class);
        $this->canReprice = $user->authorise('alliancepay.reprice', 'com_alliancepay');
        $this->canArchive = $user->authorise('alliancepay.archive', 'com_alliancepay');
        $this->repriceMinDays = Services::get(Config::class)->getSubscriptionRepriceMinDays();

        if ($this->getLayout() === 'create') {
            $this->form = (array) $app->getUserState('com_alliancepay.plan.form', []);
            $this->formRows = RulesInput::toEditorRows($this->form['rules'] ?? []);
            $this->templates = Services::get(TemplateRepository::class)->all(true);
            ToolbarHelper::title(Text::_('COM_ALLIANCEPAY_NEW_PLAN'), 'plus');
        } else {
            $id = $app->getInput()->getInt('id');
            $this->plan = Services::get(PlanRepository::class)->find($id);

            if ($this->plan === null) {
                $app->enqueueMessage(Text::_('COM_ALLIANCEPAY_ERR_NOT_FOUND'), 'error');
                $app->redirect('index.php?option=com_alliancepay&view=plans');
            }

            $this->history = Services::get(VersionHistoryRepository::class)->findByPlan($id);
            $this->formRows = RulesInput::toEditorRows($this->plan->rules);
            ToolbarHelper::title($this->plan->name, 'list');
        }

        parent::display($tpl);
    }
}
