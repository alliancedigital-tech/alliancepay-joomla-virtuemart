<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Bootstrap;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AlliancePublicKeyCache;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\CompletionProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\PaymentProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\RefundProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback\CallbackProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Customer\CustomerDataBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsSigner;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder\UpdateAllianceOrder;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation\ValidateCustomerData;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanKeyGenerator;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanRulesBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\RuleValidator;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\AuditLogRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\TemplateRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\VersionHistoryRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\AmountVerifier;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\SubscriptionCallbackHandler;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\PlanFormatter;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanArchiveService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\RecurringPaymentService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionDiagnosticsService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionCancelService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionOrderProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionStatusChanger;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\TemplateService;
use GuzzleHttp\Psr7\HttpFactory;
use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use SimpleJWT\Keys\KeyFactory;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Services\Bootstrap
 *
 * @since version 1.5.0
 */
class ServiceRegistry implements ServiceProviderInterface
{
    /**
     * @return Container
     *
     * @since version 1.5.0
     */
    public static function createContainer(): Container
    {
        $autoload = JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        $container = Factory::getContainer()->createChild();
        (new self())->register($container);

        return $container;
    }

    public function register(Container $container): void
    {
        if (!class_exists('VmConfig')) {
            require_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';
        }
        \VmConfig::loadConfig();

        $container->share(AlliancePublicKeyCache::class, function () use ($container) {
            $db = $container->get(DatabaseInterface::class);
            return new AlliancePublicKeyCache($db);
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
            return new Config();
        });
        $container->share(KeySetFactory::class, function () use ($container) {
            return new KeySetFactory();
        });
        $container->share(KeyFactory::class, function () use ($container) {
            return new KeyFactory();
        });
        $container->share(GenerateRequestIdentification::class, function () use ($container) {
            return new GenerateRequestIdentification();
        });
        $container->share(JwsSigner::class, function () use ($container) {
            return new JwsSigner(
                $container->get(KeySetFactory::class)
            );
        });
        $container->share(JwsVerifier::class, function () use ($container) {
            return new JwsVerifier(
                $container->get(KeySetFactory::class),
                $container->get(HttpClientFactory::class),
                $container->get(HttpFactory::class),
                $container->get(Config::class),
                $container->get(AlliancePublicKeyCache::class)
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
                $container->get(JwsSigner::class),
                $container->get(JwsVerifier::class),
                $container->get(Config::class)
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
        $container->share(ValidateCustomerData::class, function () use ($container) {
            return new ValidateCustomerData();
        });
        $container->share(CustomerDataBuilder::class, function () use ($container) {
            return new CustomerDataBuilder($container->get(ValidateCustomerData::class));
        });
        $container->share(PaymentProcessor::class, function () use ($container) {
            return new PaymentProcessor(
                $container->get(HttpClient::class),
                $container->get(Config::class),
                $container->get(ConvertDataService::class),
                $container->get(AllianceOrderTable::class),
                $container->get(UpdateAllianceOrder::class),
                $container->get(UrlProvider::class),
                $container->get(CustomerDataBuilder::class),
                $container->get(SubscriptionRepository::class),
                $container->get(SubscriptionCallbackHandler::class)
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
        $container->share(CompletionProcessor::class, function () use ($container) {
            return new CompletionProcessor(
                $container->get(AllianceOrderTable::class),
                $container->get(Config::class),
                $container->get(HttpClient::class),
                $container->get(UrlProvider::class),
                $container->get(DateTimeNormalizer::class),
            );
        });

        $container->share(SubscriptionApiClient::class, function () use ($container) {
            return new SubscriptionApiClient(
                $container->get(HttpClientFactory::class),
                $container->get(HttpFactory::class),
                $container->get(JwsSigner::class),
                $container->get(JwsVerifier::class),
                $container->get(Config::class)
            );
        });

        $container->share(RuleValidator::class, fn () => new RuleValidator());
        $container->share(PlanRulesBuilder::class, fn () => new PlanRulesBuilder());
        $container->share(PlanKeyGenerator::class, fn () => new PlanKeyGenerator(
            $container->get(RuleValidator::class)
        ));

        foreach (
            [
                PlanRepository::class,
                TemplateRepository::class,
                SubscriptionRepository::class,
                ScheduledPaymentRepository::class,
                VersionHistoryRepository::class,
                AuditLogRepository::class,
            ] as $repositoryClass
        ) {
            $container->share(
                $repositoryClass,
                static fn () => new $repositoryClass($container->get(DatabaseInterface::class))
            );
        }

        $container->share(PlanService::class, fn () => new PlanService(
            $container->get(SubscriptionApiClient::class),
            $container->get(PlanRepository::class),
            $container->get(AuditLogRepository::class),
            $container->get(VersionHistoryRepository::class),
            $container->get(RuleValidator::class),
            $container->get(PlanKeyGenerator::class),
            $container->get(Config::class),
            $container->get(DatabaseInterface::class)
        ));
        $container->share(TemplateService::class, fn () => new TemplateService(
            $container->get(TemplateRepository::class),
            $container->get(PlanRepository::class),
            $container->get(AuditLogRepository::class),
            $container->get(RuleValidator::class),
            $container->get(PlanRulesBuilder::class)
        ));

        $container->share(PlanFormatter::class, fn () => new PlanFormatter());
        $container->share(AmountVerifier::class, fn () => new AmountVerifier());
        $container->share(SubscriptionStatusChanger::class, fn () => new SubscriptionStatusChanger(
            $container->get(SubscriptionRepository::class),
            $container->get(PlanRepository::class)
        ));
        $container->share(CheckoutGuard::class, fn () => new CheckoutGuard(
            $container->get(PlanRepository::class),
            $container->get(Config::class)->isSubscriptionsEnabled()
        ));
        $container->share(SubscriptionCallbackHandler::class, fn () => new SubscriptionCallbackHandler(
            $container->get(SubscriptionRepository::class),
            $container->get(ScheduledPaymentRepository::class),
            $container->get(PlanRepository::class),
            $container->get(SubscriptionStatusChanger::class),
            $container->get(AmountVerifier::class),
            $container->get(DatabaseInterface::class)
        ));
        $container->share(SubscriptionOrderProcessor::class, fn () => new SubscriptionOrderProcessor(
            $container->get(SubscriptionApiClient::class),
            $container->get(PlanRepository::class),
            $container->get(SubscriptionRepository::class),
            $container->get(CheckoutGuard::class),
            $container->get(CustomerDataBuilder::class),
            $container->get(UrlProvider::class),
            $container->get(Config::class)
        ));
        $container->share(SubscriptionCancelService::class, fn () => new SubscriptionCancelService(
            $container->get(SubscriptionApiClient::class),
            $container->get(SubscriptionRepository::class),
            $container->get(SubscriptionStatusChanger::class),
            $container->get(AuditLogRepository::class),
            $container->get(Config::class)
        ));

        $container->share(RecurringPaymentService::class, fn () => new RecurringPaymentService(
            $container->get(SubscriptionApiClient::class),
            $container->get(SubscriptionRepository::class),
            $container->get(ScheduledPaymentRepository::class),
            $container->get(AuditLogRepository::class),
            $container->get(Config::class)
        ));
        $container->share(SubscriptionDiagnosticsService::class, fn () => new SubscriptionDiagnosticsService(
            $container->get(SubscriptionApiClient::class),
            $container->get(SubscriptionRepository::class),
            $container->get(PlanRepository::class),
            $container->get(ScheduledPaymentRepository::class)
        ));
        $container->share(PlanArchiveService::class, fn () => new PlanArchiveService(
            $container->get(SubscriptionApiClient::class),
            $container->get(PlanRepository::class),
            $container->get(AuditLogRepository::class)
        ));
    }
}
