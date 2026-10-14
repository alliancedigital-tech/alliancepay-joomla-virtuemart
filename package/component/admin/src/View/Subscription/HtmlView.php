<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Subscription;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionDiagnosticsService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Support diagnostics page (§4.9): operations are fetched fresh from the provider on every open.
 *
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public array $data = [];
    public bool $canRunNow = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $id = $app->getInput()->getInt('id');
        $this->data = Services::get(SubscriptionDiagnosticsService::class)->load($id);
        $this->canRunNow = $app->getIdentity()->authorise('alliancepay.runnow', 'com_alliancepay');

        if ($this->data['subscription'] === null) {
            $app->enqueueMessage(Text::_('COM_ALLIANCEPAY_ERR_NOT_FOUND'), 'error');
            $app->redirect('index.php?option=com_alliancepay&view=subscriptions');
        }

        ToolbarHelper::title(Text::sprintf('COM_ALLIANCEPAY_SUBSCRIPTION_TITLE', $id), 'users');

        parent::display($tpl);
    }
}
