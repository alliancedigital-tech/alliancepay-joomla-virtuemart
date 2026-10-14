<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Controller;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\RecurringPaymentService;
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
    /**
     * @return void
     */
    public function runNow(): void
    {
        $this->checkToken();
        $subscriptionId = $this->input->post->getInt('id');

        if (!$this->app->getIdentity()->authorise('alliancepay.runnow', 'com_alliancepay')) {
            $this->setRedirect($this->url($subscriptionId), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        try {
            Services::get(RecurringPaymentService::class)->runNow(
                $this->input->post->getInt('scheduled_payment_row'),
                (int) $this->app->getIdentity()->id
            );
            $this->setRedirect($this->url($subscriptionId), Text::_('COM_ALLIANCEPAY_RUNNOW_SENT'));
        } catch (Throwable $e) {
            $this->setRedirect($this->url($subscriptionId), Text::sprintf('COM_ALLIANCEPAY_ERR_PROVIDER', $e->getMessage()), 'error');
        }
    }

    public function cancel(): void
    {
        $this->checkToken();
        $subscriptionId = $this->input->post->getInt('id');

        try {
            $subscription = Services::get(SubscriptionRepository::class)->find($subscriptionId);

            if ($subscription === null) {
                throw new \RuntimeException(Text::_('COM_ALLIANCEPAY_ERR_NOT_FOUND'));
            }

            Services::get(SubscriptionCancelService::class)->cancel($subscription, (int) $this->app->getIdentity()->id);
            $this->setRedirect($this->url($subscriptionId), Text::_('COM_ALLIANCEPAY_SUBSCRIPTION_CANCELED'));
        } catch (Throwable $e) {
            $this->setRedirect($this->url($subscriptionId), Text::sprintf('COM_ALLIANCEPAY_ERR_PROVIDER', $e->getMessage()), 'error');
        }
    }

    private function url(int $id): string
    {
        return Route::_('index.php?option=com_alliancepay&view=subscription&id=' . $id, false);
    }
}
