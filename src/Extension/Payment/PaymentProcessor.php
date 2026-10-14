<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment;

defined('_JEXEC') or die;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder\UpdateAllianceOrder;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\Processor\AbstractProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Customer\CustomerDataBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\CallbackPayload;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\CallbackResult;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\SubscriptionCallbackHandler;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Joomla\CMS\Log\Log;
use Throwable;
use VmModel;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment
 *
 * @since version 1.0.0
 */
class PaymentProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Config $config,
        private readonly ConvertDataService $convertDataService,
        private readonly AllianceOrderTable $allianceOrder,
        private readonly UpdateAllianceOrder $updateAllianceOrder,
        private readonly UrlProvider $urlProvider,
        private readonly CustomerDataBuilder $customerDataBuilder,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionCallbackHandler $subscriptionCallbackHandler,
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param $order
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function processPayment($order)
    {
        try {
            $orderId = $order['details']['BT']->virtuemart_order_id;
            $hppOrderData = $this->preparePlaceOrderData($order);
            $hppOrderData['customerData'] = $this->prepareCustomerData($order);

            if ($orderId && !empty($hppOrderData)) {
                $resultRequest = $this->httpClient->createOrder($hppOrderData);

                if (isset($resultRequest['msgType']) && !!$resultRequest['msgText']) {
                    Log::add($resultRequest['msgText'], Log::ERROR);
                    throw new Exception($resultRequest['msgText']);
                }

                $preparedData = $this->convertDataService->camelToSnakeArrayKeys(
                    $resultRequest
                );

                $preparedData['order_id'] = $orderId;

                if ($this->config->getPaymentType() === Config::HPP_PAY_TYPE_PREAUTH) {
                    $btPa = $order['details']['BT'];
                    $paCurrRate = (float)($btPa->user_currency_rate ?: 1.0);
                    $paAmount = round((float)$btPa->order_total * $paCurrRate, 2);
                    $preparedData['original_authorized_amount'] = $this->prepareCoinAmount($paAmount);
                }

                if (!empty($hppOrderData['currencyCode'])) {
                    $preparedData['currency_code'] = (int) $hppOrderData['currencyCode'];
                }

                $this->allianceOrder->bindChecknStoreAllianceOrder($preparedData);

                if ($this->config->getPaymentType() === Config::HPP_PAY_TYPE_PREAUTH) {
                    $preAuthPendingStatus = $this->config->getPreAuthPendingStatus();
                    if (!empty($preAuthPendingStatus)) {
                        $modelOrder = VmModel::getModel('orders');
                        $modelOrder->updateStatusForOneOrder(
                            $orderId,
                            ['order_status' => $preAuthPendingStatus],
                            true
                        );
                    }
                }

                return $resultRequest;
            }
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'unsupported_currency:')) {
                $numericCode = substr($e->getMessage(), strlen('unsupported_currency:'));
                Log::add(
                    'AlliancePay: unsupported currency numeric code ' . $numericCode
                    . '. Supported: UAH (980), USD (840), EUR (978).',
                    Log::ERROR,
                    'alliance_payment'
                );

                return ['error' => 'unsupported_currency'];
            }

            Log::add('Create order service error: ' . $e->getMessage(), Log::ERROR);
        } catch (Exception $e) {
            Log::add('Create order service error: ' . $e->getMessage(), Log::ERROR);
        }

        return [];
    }

    /**
     * Manual "check order status" (admin order view, §4.9). A subscription order never has a row in
     * jm_virtuemart_payment_plg_alliance_order — it lives entirely in
     * jm_virtuemart_payment_plg_alliance_subscription instead (see SubscriptionOrderProcessor) — so it
     * must be resolved and refreshed separately from a regular one-off order.
     *
     * @param int $orderId
     *
     * @return bool
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function updateOrderData(int $orderId): bool
    {
        $subscription = $this->subscriptionRepository->findByVirtuemartOrderId($orderId);

        if ($subscription !== null) {
            return $this->updateSubscriptionOrderData($subscription);
        }

        $allianceOrder = $this->allianceOrder->load($orderId, 'order_id');
        $allianceOrderProperties = $allianceOrder->getProperties();

        if (empty($allianceOrderProperties['hpp_order_id'])) {
            Log::add(
                'Update order service error: ' . $orderId . ' has empty hpp_order_id.',
                Log::ERROR
            );

            return false;
        }

        $orderData = $this->httpClient->getOrderOperations($allianceOrderProperties['hpp_order_id']);

        if (isset($orderData['msgType']) && !!$orderData['msgText']) {
            Log::add($orderData['msgText'], Log::ERROR);

            return false;
        }

        try {
            return $this->updateAllianceOrder->updateAllianceOrder($orderId, $orderData);
        } catch (Exception $exception) {
            Log::add($exception->getMessage(), Log::ERROR);

            return false;
        }
    }

    /**
     * Re-fetches the provider's current state for a subscription order's HPP order id and applies it
     * through the exact same, already-idempotent state machine a real webhook delivery goes through
     * (SubscriptionCallbackHandler) — so a manual recheck can never disagree with what a callback would
     * have done. HttpClient::getOrderOperations() returns the full envelope (hppOrderId, orderStatus,
     * recurringTransactionType, scheduledPaymentId, operation(s), ...), which is exactly the shape
     * CallbackPayload expects (§10.6 of the implementation spec).
     *
     * @throws GuzzleException
     */
    private function updateSubscriptionOrderData(Subscription $subscription): bool
    {
        if (empty($subscription->providerOrderId)) {
            Log::add(
                'Update order service error: subscription #' . $subscription->id . ' (order #'
                . $subscription->virtuemartOrderId . ') has empty provider_order_id.',
                Log::ERROR
            );

            return false;
        }

        $orderData = $this->httpClient->getOrderOperations($subscription->providerOrderId);

        if (isset($orderData['msgType']) && !!$orderData['msgText']) {
            Log::add($orderData['msgText'], Log::ERROR);

            return false;
        }

        Log::add(
            'Subscription refresh, provider response for ' . $subscription->providerOrderId . ': '
            . json_encode($orderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            Log::INFO,
            'alliance_payment'
        );

        if (empty($orderData['hppOrderId'])) {
            $orderData['hppOrderId'] = $subscription->providerOrderId;
        }

        $handled = false;
        $newStatus = '';

        try {
            foreach ($this->splitIntoOperationPayloads($orderData) as $payload) {
                $result = $this->subscriptionCallbackHandler->handle($payload);

                if (!$result->handled) {
                    continue;
                }

                $handled = true;
                $status = match ($result->vmOrderStatus) {
                    CallbackResult::ORDER_STATUS_SUCCESS => $this->config->getSuccessPaymentStatus(),
                    CallbackResult::ORDER_STATUS_FAIL => $this->config->getFailPaymentStatus(),
                    default => '',
                };

                if ($status !== '') {
                    $newStatus = $status;
                }
            }
        } catch (Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR);

            return false;
        }

        if (!$handled) {
            // Nothing the provider returned changed the subscription's state — not an error.
            return true;
        }

        if ($newStatus !== '') {
            VmModel::getModel('orders')->updateStatusForOneOrder(
                $subscription->virtuemartOrderId,
                ['order_status' => $newStatus],
                true
            );
        }

        return true;
    }

    /**
     * The operations endpoint returns the whole history in one envelope, while the callback state machine
     * applies a single operation per payload. Replays every operation (oldest first) as its own payload.
     * The first operation is the initial payment; later ones without their own markers are recurring.
     *
     * @param array $orderData
     * @return CallbackPayload[]
     */
    private function splitIntoOperationPayloads(array $orderData): array
    {
        $operations = [];

        if (isset($orderData['operations']) && is_array($orderData['operations'])) {
            $operations = array_values(array_filter($orderData['operations'], 'is_array'));
        } elseif (isset($orderData['operation']) && is_array($orderData['operation'])) {
            $operations = [$orderData['operation']];
        }

        if ($operations === []) {
            return [CallbackPayload::fromArray($orderData)];
        }

        usort(
            $operations,
            static fn (array $a, array $b): int => strcmp(
                (string) ($a['creationDateTime'] ?? ''),
                (string) ($b['creationDateTime'] ?? '')
            )
        );

        $envelope = $orderData;
        unset($envelope['operations'], $envelope['operation']);

        $payloads = [];

        foreach ($operations as $index => $operation) {
            $data = $envelope;

            foreach (['scheduledPaymentId', 'recurringTransactionType', 'paymentNumber'] as $field) {
                if (isset($operation[$field])) {
                    $data[$field] = $operation[$field];
                }
            }

            if ($index > 0 && !isset($operation['recurringTransactionType']) && !isset($operation['scheduledPaymentId'])) {
                $data['recurringTransactionType'] = 'RECURRING';
            }

            if ($index === 0 && !isset($operation['scheduledPaymentId'])) {
                unset($data['scheduledPaymentId']);
            }

            $data['operation'] = $operation;
            $payloads[] = CallbackPayload::fromArray($data);
        }

        return $payloads;
    }

    /**
     * @param $order
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function preparePlaceOrderData($order): array
    {
        $bt = $order['details']['BT'];
        $userCurrRate = (float)($bt->user_currency_rate ?: 1.0);
        $paymentAmount = round((float)$bt->order_total * $userCurrRate, 2);
        $coinAmount = $this->prepareCoinAmount($paymentAmount);
        $langCode = explode('-', $bt->order_language)[0];
        $orderId = $bt->virtuemart_order_id ?? 0;

        $data = [
            'coinAmount' => $coinAmount,
            'hppPayType' => $this->config->getPaymentType(),
            'paymentMethods' => Config::PAYMENT_METHODS,
            'language' => $langCode,
            'successUrl' => $this->urlProvider->getSuccessUrl($order),
            'failUrl' => $this->urlProvider->getFailUrl($order),
            'notificationUrl' => $this->urlProvider->getCallbackUrl($orderId),
            'merchantId' => $this->config->getMerchantId(),
            'statusPageType' => $this->config->getStatusPageType(),
            'merchantRequestId'=> $this->generateMerchantRequestId()
        ];

        if ($data['hppPayType'] === Config::HPP_PAY_TYPE_A2A) {
            $data['directType'] = Config::DIRECT_TYPE_BANK_LINK;
            $data['priorityBankCode'] = Config::PRIORITY_BANK_CODE;
            $data['merchantComment'] = 'Payment for order #' . ($orderId ?? '');
        }

        if ($data['hppPayType'] === Config::HPP_PAY_TYPE_PREAUTH) {
            $data['preAuthExpDate'] = $this->calculatePreAuthExpDate();
        }

        if (
            $data['hppPayType'] === Config::HPP_PAY_TYPE_PURCHASE
            || $data['hppPayType'] === Config::HPP_PAY_TYPE_PREAUTH
        ) {
            $data['currencyCode'] = $this->resolveCurrencyCode($order);
        }

        return $data;
    }

    /**
     *
     * @return string
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @since version 1.3.0
     */
    private function calculatePreAuthExpDate(): string
    {
        $expPeriod = $this->config->getPreAuthExpDate();

        $intervalMap = [
            '2h'  => '+2 hours',
            '4h'  => '+4 hours',
            '6h'  => '+6 hours',
            '12h' => '+12 hours',
            '1d'  => '+1 day',
            '2d'  => '+2 days',
            '7d'  => '+7 days',
            '14d' => '+14 days',
            '28d' => '+28 days',
        ];

        $modifier = $intervalMap[$expPeriod] ?? '+2 hours';

        $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        $now->modify($modifier);
        $now->modify('+30 seconds');

        $milliseconds = substr((string) round($now->format('u') / 10000), 0, 2);
        if (strlen($milliseconds) < 2) {
            $milliseconds = str_pad($milliseconds, 2, '0');
        }

        return $now->format('Y-m-d H:i:s') . '.' . $milliseconds . $now->format('P');
    }

    /**
     * @param $order
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function prepareCustomerData($order): array
    {
        return $this->customerDataBuilder->build($order);
    }

    /**
     * @param array $order  VirtueMart order array
     *
     * @return int  ISO 4217 numeric code
     *
     * @throws \RuntimeException  when the order currency is not supported
     *
     * @since version 1.4.0
     */
    private function resolveCurrencyCode(array $order): int
    {
        $bt = $order['details']['BT'];
        $currencyId = (int) ($bt->user_currency_id ?: ($bt->order_currency ?? 0));

        if ($currencyId > 0) {
            $currencyModel = VmModel::getModel('currency');
            $currency      = $currencyModel->getCurrency($currencyId);
            $numericCode   = (int) ($currency->currency_numeric_code ?? 0);

            if ($numericCode > 0) {
                $currencyName = $this->config->getCurrencyNameByNumericCode($numericCode);

                if ($currencyName !== null) {
                    return $this->config->getCurrencyCode($currencyName);
                }

                throw new \RuntimeException('unsupported_currency:' . $numericCode);
            }
        }

        return Config::CURRENCY_CODE_MAP['UAH'];
    }

    /**
     * @param array $data
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function validateAndClenUpData(array $data): array
    {
        $validatedData = [];

        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $validatedData[$key] = $value;
            }
        }

        return $validatedData;
    }
}
