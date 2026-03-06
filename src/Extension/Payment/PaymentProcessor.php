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
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Joomla\CMS\Log\Log;

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
                $this->allianceOrder->bindChecknStoreAllianceOrder($preparedData);

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
            'hppPayType' => Config::HPP_PAY_TYPE,
            'paymentMethods' => Config::PAYMENT_METHODS,
            'language' => $langCode,
            'successUrl' => $this->urlProvider->getSuccessUrl($order),
            'failUrl' => $this->urlProvider->getFailUrl($order),
            'notificationUrl' => $this->urlProvider->getCallbackUrl($orderId),
            'merchantId' => $this->config->getMerchantId(),
            'statusPageType' => $this->config->getStatusPageType(),
            'merchantRequestId'=> $this->generateMerchantRequestId()
        ];

        return $data;
    }
    private function prepareCustomerData($order): array
    {
        $data = [];

        if (empty($order['details']['BT'])) {
            return $data;
        }

        $data['senderCustomerId'] = $order['details']['BT']->customer_number;
        $firstName = $order['details']['BT']->first_name ?? '';
        $lastName = $order['details']['BT']->last_name ?? '';
        $middleName = $order['details']['BT']->middle_name ?? '';
        $customerPhone = $order['details']['BT']->phone_1 ?? $order['details']['BT']->phone_2 ?? '';
        $street = $order['details']['BT']->address_1 ?? $order['details']['BT']->address_2 ?? '';

        $data['senderEmail'] = $order['details']['BT']->email;
        $data['senderFirstName'] = $firstName;
        $data['senderLastName'] = $lastName;
        $data['senderMiddleName'] = $middleName;
        $data['senderRegion'] = $customerAddress['state'] ?? '';
        $data['senderStreet'] = $street;
        $data['senderCity'] = $order['details']['BT']->city;
        $data['senderZipCode'] = $order['details']['BT']->zip;
        $data['senderPhone'] = $customerPhone;
        $data['senderCountry'] = $order['details']['BT']->virtuemart_country_id;

        return $this->validateAndClenUpData($data);
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