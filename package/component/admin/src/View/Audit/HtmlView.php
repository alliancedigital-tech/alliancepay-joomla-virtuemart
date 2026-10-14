<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Audit;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public array $items = [];

    public function display($tpl = null): void
    {
        $planId = Factory::getApplication()->getInput()->getInt('plan_id') ?: null;
        $this->items = Services::get(AuditLogRepository::class)->latest(200, 0, $planId);

        ToolbarHelper::title(Text::_('COM_ALLIANCEPAY_AUDIT_TITLE'), 'clock');

        parent::display($tpl);
    }
}
