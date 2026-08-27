<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\Processor\AbstractProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Exception;
use Joomla\CMS\Log\Log;
use Throwable;

class RefundProcessor extends AbstractProcessor
{
    public const REFUND_DATA_FIELD_MERCHANT_REQUEST_ID = 'merchantRequestId';
    public const REFUND_DATA_FIELD_OPERATION_ID = 'operationId';
    public const REFUND_DATA_FIELD_MERCHANT_ID = 'merchantId';
    public const REFUND_DATA_FIELD_COIN_AMOUNT = 'coinAmount';
    public const REFUND_DATA_FIELD_NOTIFICATION_URL = 'notificationUrl';
    public const REFUND_DATA_FIELD_DATE = 'date';

    public function __construct(
        private readonly AllianceOrderTable $allianceOrderTable,
        private readonly AllianceRefundTable $alliancerefundTable,
        private readonly Config $config,
        private readonly HttpClient $httpClient,
        private readonly UrlProvider $urlProvider,
        private readonly ConvertDataService $convertDataService,
        private readonly DateTimeNormalizer $dateTimeNormalizer
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    public function processRefund(int $orderId, ?int $amount = null): array
    {

        $allianceOrder = $this->allianceOrderTable->load($orderId, 'order_id');
        $allianceOrderData = $allianceOrder->getProperties();

        if (!empty($allianceOrderData['operation_id'])
            && (!empty($allianceOrderData['coin_amount']) || !empty($amount))
        ) {
            $conversionRate = isset($allianceOrderData['conversion_rate'])
                ? (float) $allianceOrderData['conversion_rate']
                : 0.0;

            if (!is_null($amount)) {
                if ($conversionRate > 1.0) {
                    $coinAmount = (int) round($amount * $conversionRate);
                    Log::add(
                        sprintf(
                            'RefundProcessor: partial refund converted %d foreign coins × %.6f = %d UAH coins for order_id=%d.',
                            $amount,
                            $conversionRate,
                            $coinAmount,
                            $orderId
                        ),
                        Log::INFO,
                        'alliance_payment'
                    );
                } else {
                    $coinAmount = $amount;
                }
            } else {
                $hppPayType = $allianceOrderData['hpp_pay_type'] ?? '';

                if ($hppPayType === Config::HPP_PAY_TYPE_PREAUTH) {
                    $coinAmount = $this->getCompletionCoinAmountFromCallbackData(
                        $allianceOrderData['callback_data'] ?? ''
                    ) ?? (int) $allianceOrderData['coin_amount'];
                } else {
                    $coinAmount = (int) $allianceOrderData['coin_amount'];
                }

                if ($conversionRate > 1.0) {
                    $rawCoinAmount = $coinAmount;
                    $coinAmount = (int) round($coinAmount * $conversionRate);
                    Log::add(
                        sprintf(
                            'RefundProcessor: full refund converted %d foreign coins × %.6f = %d UAH coins for order_id=%d.',
                            $rawCoinAmount,
                            $conversionRate,
                            $coinAmount,
                            $orderId
                        ),
                        Log::INFO,
                        'alliance_payment'
                    );
                }
            }

            $params = !is_null($amount) ? ['partialRefund' => 1] : ['partialRefund' => 0];
            $refundData = $this->prepareRefundData(
                $allianceOrderData['operation_id'],
                $coinAmount,
                $this->urlProvider->getCallbackUrl($orderId, $params)
            );

            try {
                $refundResult = $this->httpClient->refund($refundData);
            } catch (Throwable $exception) {
                Log::add('Failed to refund alliance order: ' . $orderId, Log::ERROR);
                Log::add($exception->getMessage(), Log::ERROR);

                return ['success' => false];
            }

            if (!empty($refundResult) && isset($refundResult['type']) && isset($refundResult['status'])) {
                $convertedRefundData = $this->convertDataService->camelToSnakeArrayKeys($refundResult);
                $convertedRefundData['order_id'] = $orderId;
                $this->alliancerefundTable->bindChecknStoreAllianceRefund($convertedRefundData);

                if ($this->checkIfSuccess($refundResult['type'], $refundResult['status'])) {
                    return ['success' => true];
                }
            }
        }

        return ['success' => false];
    }

    /**
     * @param string $operationId
     * @param int $amount
     * @param string $callbackUrl
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function prepareRefundData(string $operationId, int $amount, string $callbackUrl): array
    {
        $preparedData = [];
        $preparedData[self::REFUND_DATA_FIELD_OPERATION_ID] = $operationId;
        $preparedData[self::REFUND_DATA_FIELD_COIN_AMOUNT] = $amount;
        $preparedData[self::REFUND_DATA_FIELD_MERCHANT_REQUEST_ID] = $this->generateMerchantRequestId();
        $preparedData[self::REFUND_DATA_FIELD_MERCHANT_ID] = $this->config->getMerchantId();
        $preparedData[self::REFUND_DATA_FIELD_DATE] = $this->dateTimeNormalizer->getRefundDate();
        $preparedData[self::REFUND_DATA_FIELD_NOTIFICATION_URL] = $callbackUrl;

        return $preparedData;
    }

    /**
     * @param string $callbackDataJson
     *
     * @return int|null
     *
     * @since version
     */
    private function getCompletionCoinAmountFromCallbackData(string $callbackDataJson): ?int
    {
        if (empty($callbackDataJson)) {
            return null;
        }

        $callbackData = json_decode($callbackDataJson, true);

        if (!is_array($callbackData) || empty($callbackData['operations'])) {
            return null;
        }

        $completionCoinAmount = null;

        foreach ($callbackData['operations'] as $operation) {
            $type   = $operation['type']   ?? '';
            $status = $operation['status'] ?? '';

            if ($type === Config::OPERATION_TYPE_COMPLETION && $status === 'SUCCESS') {
                $amount = $operation['coinAmount'] ?? $operation['coin_amount'] ?? null;
                if ($amount !== null) {
                    $completionCoinAmount = (int) $amount;
                }
            }
        }

        return $completionCoinAmount;
    }

    private function checkIfSuccess(string $type, string $status): bool
    {
        if ($type !== 'REFUND') {
            return false;
        }

        return in_array($status, ['SUCCESS', 'PENDING']);
    }
}
