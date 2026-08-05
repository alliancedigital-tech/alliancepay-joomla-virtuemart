<?php
/**
 * @copyright Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *
 * @license MIT
 *
 * @since version 1.3.0
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\Processor\AbstractProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Exception;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment
 *
 * @since version 1.3.0
 */
class CompletionProcessor extends AbstractProcessor
{
    private const AMOUNT_TOLERANCE = 0.20;

    public const COMPLETION_DATA_FIELD_MERCHANT_REQUEST_ID = 'merchantRequestId';

    public const COMPLETION_DATA_FIELD_MERCHANT_ID = 'merchantId';

    public const COMPLETION_DATA_FIELD_ORIGINAL_OPERATION_ID = 'originalOperationId';

    public const COMPLETION_DATA_FIELD_COIN_AMOUNT = 'coinAmount';

    public const COMPLETION_DATA_FIELD_DATE = 'date';

    public const COMPLETION_DATA_FIELD_NOTIFICATION_URL = 'notificationUrl';

    public function __construct(
        private readonly AllianceOrderTable $allianceOrderTable,
        private readonly Config $config,
        private readonly HttpClient $httpClient,
        private readonly UrlProvider $urlProvider,
        private readonly DateTimeNormalizer $dateTimeNormalizer
    )
    {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param int $orderId
     * @param int $newCoinAmount
     *
     * @return array|true[]
     *
     * @since version 1.3.0
     */
    public function processCompletion(int $orderId, int $newCoinAmount): array
    {
        try {
            $allianceOrder = $this->allianceOrderTable->load($orderId, 'order_id');
            $allianceOrderData = $allianceOrder->getProperties();
        } catch (Throwable $e) {
            $entry = 'CompletionProcessor: failed to load alliance order for order_id='
                . $orderId
                . ': '
                . $e->getMessage();
            Log::add(
                $entry,
                Log::ERROR,
                'alliance_payment'
            );

            return ['success' => false, 'message' => 'Failed to load order data.'];
        }

        if (($allianceOrderData['hpp_pay_type'] ?? '') !== Config::HPP_PAY_TYPE_PREAUTH) {
            Log::add(
                'CompletionProcessor: order_id=' . $orderId . ' is not a PREAUTH order.',
                Log::WARNING,
                'alliance_payment'
            );

            return ['success' => false, 'message' => 'Order is not a PREAUTH type.'];
        }

        $originalAmount = (int)($allianceOrderData['original_authorized_amount'] ?? 0);

        if ($originalAmount <= 0) {
            Log::add(
                'CompletionProcessor: order_id=' . $orderId . ' has no original_authorized_amount.',
                Log::ERROR,
                'alliance_payment'
            );

            return ['success' => false, 'message' => 'Original authorized amount is not set.'];
        }

        if (!$this->isAmountWithinTolerance($newCoinAmount, $originalAmount)) {
            $message = sprintf(
                'Completion amount %d is outside the allowed ±20%% range of original amount %d.',
                $newCoinAmount,
                $originalAmount
            );
            Log::add('CompletionProcessor: ' . $message, Log::WARNING, 'alliance_payment');

            return ['success' => false, 'message' => $message];
        }

        $operationId = $allianceOrderData['operation_id'] ?? '';

        if (empty($operationId)) {
            Log::add(
                'CompletionProcessor: order_id=' . $orderId . ' has empty operation_id.',
                Log::ERROR,
                'alliance_payment'
            );

            return ['success' => false, 'message' => 'Original operation ID is not set.'];
        }

        $completionData = $this->prepareCompletionData($orderId, $operationId, $newCoinAmount);

        try {
            $result = $this->httpClient->completion($completionData);
        } catch (Throwable $e) {
            Log::add(
                'CompletionProcessor: API call failed for order_id=' . $orderId . ': ' . $e->getMessage(),
                Log::ERROR,
                'alliance_payment'
            );

            return ['success' => false, 'message' => 'Completion API call failed: ' . $e->getMessage()];
        }

        if (!empty($result) && isset($result['type']) && isset($result['status'])) {
            if ($this->checkIfSuccess($result['type'], $result['status'])) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => 'Completion request returned status: ' . $result['status'],
            ];
        }

        return ['success' => false, 'message' => 'Unexpected completion response.'];
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @param int $coinAmount
     *
     * @return array
     *
     * @since version 1.3.0
     */
    private function prepareCompletionData(int $orderId, string $operationId, int $coinAmount): array
    {
        return [
            self::COMPLETION_DATA_FIELD_MERCHANT_REQUEST_ID => $this->generateMerchantRequestId(),
            self::COMPLETION_DATA_FIELD_MERCHANT_ID => $this->config->getMerchantId(),
            self::COMPLETION_DATA_FIELD_ORIGINAL_OPERATION_ID => $operationId,
            self::COMPLETION_DATA_FIELD_COIN_AMOUNT => $coinAmount,
            self::COMPLETION_DATA_FIELD_DATE => $this->dateTimeNormalizer->getRefundDate(),
            self::COMPLETION_DATA_FIELD_NOTIFICATION_URL => $this->urlProvider->getCallbackUrl($orderId),
        ];
    }

    /**
     * @param int $newAmount
     * @param int $originalAmount
     *
     * @return bool
     *
     * @since version 1.3.0
     */
    private function isAmountWithinTolerance(int $newAmount, int $originalAmount): bool
    {
        $lowerBound = (int)round($originalAmount * (1 - self::AMOUNT_TOLERANCE));
        $upperBound = (int)round($originalAmount * (1 + self::AMOUNT_TOLERANCE));

        return $newAmount >= $lowerBound && $newAmount <= $upperBound;
    }

    /**
     * @param string $type
     * @param string $status
     *
     * @return bool
     *
     * @since version 1.3.0
     */
    private function checkIfSuccess(string $type, string $status): bool
    {
        if ($type !== Config::OPERATION_TYPE_COMPLETION) {
            return false;
        }

        return in_array($status, ['SUCCESS', 'PENDING']);
    }
}
