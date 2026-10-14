<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Customer\CustomerDataBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CartInspection;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\SubscriptionStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\SubscriptionApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service
 *
 * @since version 1.5.0
 */
class SubscriptionOrderProcessor
{
    public const ERROR_PROVIDER = 'provider';
    public const ERROR_NO_REDIRECT = 'no_redirect';

    private const DIRECT_TYPE_REDIRECT = 'REDIRECT';
    private const LOG_CATEGORY = 'alliance_payment';

    public function __construct(
        private readonly SubscriptionApiClient $apiClient,
        private readonly PlanRepository $planRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CheckoutGuard $checkoutGuard,
        private readonly CustomerDataBuilder $customerDataBuilder,
        private readonly UrlProvider $urlProvider,
        private readonly Config $config
    ) {
    }

    /**
     * @param array $order VirtueMart order: ['details' => ['BT' => ...], 'items' => [...]]
     */
    public function isSubscriptionOrder(array $order): bool
    {
        return CartInspection::fromLines($order['items'] ?? [])->hasSubscription();
    }

    /**
     * @param array $order VirtueMart order
     *
     * @return array ['redirectUrl' => string] on success, otherwise ['error' => <CheckoutGuard::VIOLATION_* | ERROR_*>, 'message' => string]
     */
    public function process(array $order): array
    {
        $bt = $order['details']['BT'];
        $vmOrderId = (int) $bt->virtuemart_order_id;
        $userId = (int) ($bt->virtuemart_user_id ?? 0);

        // Last line of defence: the cart-level guards can be bypassed by a crafted request.
        $inspection = CartInspection::fromLines($order['items'] ?? []);
        $violation = $this->checkoutGuard->check($inspection, $userId);

        if ($violation !== null) {
            Log::add('Subscription checkout refused for order #' . $vmOrderId . ': ' . $violation, Log::WARNING, self::LOG_CATEGORY);

            return ['error' => $violation, 'message' => ''];
        }

        $plan = $this->planRepository->find($inspection->subscriptions[0]['planId']);

        $subscription = $this->subscriptionRepository->findByVirtuemartOrderId($vmOrderId);

        if ($subscription === null) {
            $subscriptionId = $this->subscriptionRepository->insert([
                'virtuemart_order_id' => $vmOrderId,
                'virtuemart_user_id' => $userId,
                'plan_id' => $plan->id,
                // Snapshot of the rules at the moment of checkout: needed to verify future amounts
                // even after the plan itself changes.
                'rules_snapshot' => json_encode($plan->rules, JSON_UNESCAPED_UNICODE),
                'merchant_request_id' => GenerateRequestIdentification::generateRequestId(),
                'status' => SubscriptionStatus::Pending->value,
            ]);
            $subscription = $this->subscriptionRepository->find($subscriptionId);
        }

        $request = [
            'planId' => $plan->providerPlanId,
            'merchantId' => $this->config->getMerchantId(),
            // Same id on a retry of the same checkout -> the provider can answer idempotently (§10.8).
            'merchantRequestId' => $subscription->merchantRequestId,
            'purpose' => 'Subscription: ' . $plan->name,
            'merchantComment' => 'Order #' . ($bt->order_number ?? $vmOrderId),
            // sub=1 marks subscription callbacks; the receiver still resolves the subscription by hppOrderId only.
            'notificationUrl' => $this->urlProvider->getCallbackUrl($vmOrderId, ['sub' => '1']),
            'notificationEncryption' => false,
            'statusPageType' => $this->config->getStatusPageType(),
            'successUrl' => $this->urlProvider->getSuccessUrl($order),
            'failUrl' => Uri::root() . 'index.php?option=com_virtuemart&view=cart',
            'hppPaymentMethods' => Config::PAYMENT_METHODS,
            'directType' => self::DIRECT_TYPE_REDIRECT,
            'customerData' => $this->customerDataBuilder->build($order),
        ];

        try {
            $response = $this->apiClient->createSubscriptionOrder($request);
        } catch (ProviderApiException $e) {
            $this->subscriptionRepository->update($subscription->id, ['status' => SubscriptionStatus::Failed->value]);

            return ['error' => self::ERROR_PROVIDER, 'message' => $e->getAdminMessage()];
        }

        $hppOrderId = (string) ($response['hppOrderId'] ?? '');
        $redirectUrl = (string) ($response['redirectUrl'] ?? '');

        if ($hppOrderId === '' || $redirectUrl === '') {
            $this->subscriptionRepository->update($subscription->id, ['status' => SubscriptionStatus::Failed->value]);
            Log::add(
                'Subscription order create returned no hppOrderId/redirectUrl: ' . json_encode($response),
                Log::ERROR,
                self::LOG_CATEGORY
            );

            return ['error' => self::ERROR_NO_REDIRECT, 'message' => ''];
        }

        $this->subscriptionRepository->update($subscription->id, [
            'provider_order_id' => $hppOrderId,
            'status' => SubscriptionStatus::Pending->value,
        ]);

        return ['redirectUrl' => $redirectUrl];
    }
}
