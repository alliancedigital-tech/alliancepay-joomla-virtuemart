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
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation\ValidateCustomerData;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Joomla\CMS\Log\Log;
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
        private readonly ValidateCustomerData $validateCustomerData,
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
                    $coinAmount = $this->prepareCoinAmount((float) $order['details']['BT']->order_total);
                    $preparedData['original_authorized_amount'] = $coinAmount;
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
        } catch (Exception $e) {
            Log::add('Create order service error: ' . $e->getMessage(), Log::ERROR);
        }

        return [];
    }

    /**
     * @param int $orderId
     *
     * @return bool
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function updateOrderData(int $orderId): bool
    {
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
     * @param $order
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function preparePlaceOrderData($order): array
    {
        $coinAmount = $this->prepareCoinAmount((float) $order['details']['BT']->order_total);
        $langCode = explode('-', $order['details']['BT']->order_language)[0];
        $orderId = $order['details']['BT']->virtuemart_order_id ?? 0;

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
        $data = [];

        if (empty($order['details']['BT'])) {
            return $data;
        }

        $data['senderCustomerId'] = $order['details']['BT']->customer_number;
        $bt = $order['details']['BT'];
        $countryModel = VmModel::getModel('country');
        $stateModel = VmModel::getModel('state');
        $country = $countryModel->getData($bt->virtuemart_country_id);
        $state = $stateModel->getData($bt->virtuemart_state_id);

        $data['senderEmail'] = $bt->email ?? '';
        $data['senderFirstName'] = $bt->first_name ?? '';
        $data['senderLastName'] = $bt->last_name ?? '';
        $data['senderMiddleName'] = $bt->middle_name ?? '';
        $data['senderPhone'] = $bt->phone_1 ?: ($bt->phone_2 ?: '');
        $data['senderCity'] = $bt->city ?? '';
        $data['senderZipCode'] = $bt->zip ?? '';
        $data['senderStreet'] = $bt->address_1 ?? '';
        $data['senderAdditionalAddress'] = $bt->address_2 ?? '';
        $data['senderCountry'] = $country->country_num_code ?? '';
        $data['senderRegion'] = $state->state_name ?? '';
        $data['senderIp'] = $bt->ip_address ?? '';

        return $this->validateCustomerData->validateAndClenUpData($data);
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
