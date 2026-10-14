<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Site\View\Subscriptions;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * "My subscriptions": only the logged-in customer's own records.
 *
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<int, array{subscription: object, planName: string}> */
    public array $rows = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $userId = (int) $app->getIdentity()->id;

        if ($userId < 1) {
            $return = base64_encode(Route::_('index.php?option=com_alliancepay&view=subscriptions', false));
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
        }

        $plans = Services::get(PlanRepository::class);

        foreach (Services::get(SubscriptionRepository::class)->findByUser($userId) as $subscription) {
            $this->rows[] = [
                'subscription' => $subscription,
                'planName' => $plans->find($subscription->planId)?->name ?? '',
            ];
        }

        parent::display($tpl);
    }
}
