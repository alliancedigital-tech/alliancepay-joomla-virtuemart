<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Site\Controller;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionCancelService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Throwable;

/**
 * SubscriptionController.
 *
 * @since 1.5.0
 */
class SubscriptionController extends BaseController
{
    public function cancel(): void
    {
        $this->checkToken();

        $url = Route::_('index.php?option=com_alliancepay&view=subscriptions', false);
        $userId = (int) $this->app->getIdentity()->id;

        if ($userId < 1) {
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        try {
            Services::get(SubscriptionCancelService::class)->cancelByCustomer($this->input->post->getInt('id'), $userId);
            $this->setRedirect($url, Text::_('COM_ALLIANCEPAY_SITE_CANCELED'));
        } catch (Throwable $e) {
            $this->setRedirect($url, Text::_('COM_ALLIANCEPAY_SITE_CANCEL_FAILED'), 'error');
        }
    }
}
