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

use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceAuthenticationData;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\PaymentProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\RefundProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback\CallbackProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder\UpdateAllianceOrder;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use AlliancePay\Model\Payment\Processor\AbstractProcessor;
use GuzzleHttp\Psr7\HttpFactory;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Authorization\AuthorizationService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\JWEFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\JweEncryptionService;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\AlliancePay;
use SimpleJWT\Keys\KeyFactory;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->share(AllianceAuthenticationData::class, function () use ($container) {
            $db = $container->get(DatabaseInterface::class);
            return new AllianceAuthenticationData($db);
        });
        $container->share(ConvertDataService::class, function () use ($container) {
            return new ConvertDataService();
        });
        $container->share(DateTimeNormalizer::class, function () use ($container) {
            return new DateTimeNormalizer();
        });
        $container->share(DateTimeProvider::class, function () use ($container) {
            return new DateTimeProvider();
        });
        $container->share(Config::class, function () use ($container) {
            return new Config(
                $container->get(DateTimeNormalizer::class),
                $container->get(AllianceAuthenticationData::class),
                $container->get(ConvertDataService::class)
            );
        });
        $container->share(KeySetFactory::class, function () use ($container) {
            return new KeySetFactory();
        });
        $container->share(KeyFactory::class, function () use ($container) {
            return new KeyFactory();
        });
        $container->share(JWEFactory::class, function () use ($container) {
            return new JWEFactory();
        });
        $container->share(JweEncryptionService::class, function () use ($container) {
            return new JWEEncryptionService(
                $container->get(KeyFactory::class),
                $container->get(KeySetFactory::class),
                $container->get(JWEFactory::class)
            );
        });
        $container->share(HttpClientFactory::class, function () use ($container) {
            return new HttpClientFactory();
        });
        $container->share(HttpFactory::class, function () use ($container) {
            return new HttpFactory();
        });
        $container->share(HttpClient::class, function () use ($container) {
            return new HttpClient(
                $container->get(HttpClientFactory::class),
                $container->get(HttpFactory::class),
                $container->get(JweEncryptionService::class),
                $container->get(Config::class)
            );
        });
        $container->share(AuthorizationService::class, function (Container $container) {
            return new AuthorizationService(
                $container->get(HttpClient::class),
                $container->get(JweEncryptionService::class),
                $container->get(Config::class),
            );
        });
        $container->share(AllianceOrderTable::class, function () use ($container) {
            $db = $container->get(DatabaseInterface::class);
            return new AllianceOrderTable(
                $db,
                $container->get(DateTimeNormalizer::class),
            );
        });
        $container->share(AllianceRefundTable::class, function () use ($container) {
            $db = $container->get(DatabaseInterface::class);
            return new AllianceRefundTable(
                $db,
                $container->get(DateTimeNormalizer::class),
            );
        });
        $container->share(UrlProvider::class, function () use ($container) {
            return new UrlProvider();
        });
        $container->share(UpdateAllianceOrder::class, function () use ($container) {
            return new UpdateAllianceOrder(
                $container->get(AllianceOrderTable::class),
                $container->get(AllianceRefundTable::class),
                $container->get(DateTimeProvider::class),
                $container->get(ConvertDataService::class),
            );
        });
        $container->share(CallbackProcessor::class, function () use ($container) {
            return new CallbackProcessor(
                $container->get(UpdateAllianceOrder::class),
            );
        });
        $container->share(PaymentProcessor::class, function () use ($container) {
            return new PaymentProcessor(
                $container->get(HttpClient::class),
                $container->get(Config::class),
                $container->get(ConvertDataService::class),
                $container->get(AllianceOrderTable::class),
                $container->get(UpdateAllianceOrder::class),
                $container->get(UrlProvider::class),
            );
        });
        $container->share(RefundProcessor::class, function () use ($container) {
            return new RefundProcessor(
                $container->get(AllianceOrderTable::class),
                $container->get(AllianceRefundTable::class),
                $container->get(Config::class),
                $container->get(HttpClient::class),
                $container->get(UrlProvider::class),
                $container->get(ConvertDataService::class),
                $container->get(DateTimeNormalizer::class),
            );
        });

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
                    $container->get(AuthorizationService::class),
                    $subject,
                    $config
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
