<?php
/**
 * @package
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */


defined('_JEXEC') or die;

if (file_exists(JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php')) {
    require_once JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';
} elseif (file_exists(JPATH_LIBRARIES . '/vendor/autoload.php')) {
    require_once JPATH_LIBRARIES . '/vendor/autoload.php';
}

use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\CompletionProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\PaymentProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\RefundProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback\CallbackProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Bootstrap\ServiceRegistry;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\SubscriptionCallbackHandler;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionCancelService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionOrderProcessor;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\AlliancePay;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->registerServiceProvider(new ServiceRegistry());

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('vmpayment', 'alliancepay');
                $subject = $container->get(DispatcherInterface::class);
                $plugin = new AlliancePay(
                    $container->get(PaymentProcessor::class),
                    $container->get(RefundProcessor::class),
                    $container->get(CallbackProcessor::class),
                    $container->get(Config::class),
                    $container->get(AllianceOrderTable::class),
                    $container->get(AllianceRefundTable::class),
                    $container->get(JwsVerifier::class),
                    $container->get(CompletionProcessor::class),
                    $container->get(SubscriptionCallbackHandler::class),
                    $container->get(SubscriptionOrderProcessor::class),
                    $container->get(CheckoutGuard::class),
                    $container->get(SubscriptionCancelService::class),
                    $container->get(SubscriptionRepository::class),
                    $container->get(ScheduledPaymentRepository::class),
                    $subject,
                    $config
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
