<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Templates;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\TemplateRepository;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public array $items = [];
    /** @var array<int, bool> template id => used by at least one plan */
    public array $used = [];

    public function display($tpl = null): void
    {
        $plans = Services::get(PlanRepository::class);
        $this->items = Services::get(TemplateRepository::class)->all();

        foreach ($this->items as $template) {
            $this->used[$template->id] = $plans->isTemplateUsed($template->id);
        }

        ToolbarHelper::title(Text::_('COM_ALLIANCEPAY_TEMPLATES_TITLE'), 'copy');

        parent::display($tpl);
    }
}
