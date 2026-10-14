<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Template;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\TemplateRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Template create / edit form. A template already used by a plan is shown read-only (§2).
 *
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public array $form = [];
    public array $formRows = [];
    public bool $readOnly = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $id = $app->getInput()->getInt('id');
        $state = (array) $app->getUserState('com_alliancepay.template.form', []);

        if ($state !== []) {
            $this->form = $state;
            $this->formRows = $state['rules'] ?? [];
        } elseif ($id > 0) {
            $template = Services::get(TemplateRepository::class)->find($id);

            if ($template === null) {
                $app->enqueueMessage(Text::_('COM_ALLIANCEPAY_ERR_NOT_FOUND'), 'error');
                $app->redirect('index.php?option=com_alliancepay&view=templates');
            }

            $this->form = [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'category_indicator' => $template->categoryIndicator,
            ];
            $this->formRows = $template->rulesTemplate;
            $this->readOnly = Services::get(PlanRepository::class)->isTemplateUsed($id)
                || $template->status->value !== 'active';
        }

        ToolbarHelper::title(Text::_($id > 0 ? 'COM_ALLIANCEPAY_TEMPLATE_EDIT' : 'COM_ALLIANCEPAY_TEMPLATE_NEW'), 'copy');

        parent::display($tpl);
    }
}
