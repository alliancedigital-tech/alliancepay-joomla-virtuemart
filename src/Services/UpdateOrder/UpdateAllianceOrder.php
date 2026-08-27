<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeProvider;
use Exception;
use Joomla\CMS\Log\Log;

class UpdateAllianceOrder
{
    public function __construct(
        private readonly AllianceOrderTable $allianceOrderTable,
        private readonly AllianceRefundTable $allianceRefundTable,
        private readonly DateTimeProvider $dateTimeProvider,
        private readonly ConvertDataService $convertDataService,
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param int $orderId
     * @param array $data
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function updateAllianceOrder(int $orderId, array $data): bool
    {
        $allianceOrderObject = $this->allianceOrderTable->load($orderId, 'order_id');
        $orderCallback = json_decode($allianceOrderObject->get('callback_data'), true) ?? [];
        $preparedCallback = $this->prepareCallbackData($orderCallback, $data);
        $jsonPreparedCallback = json_encode($preparedCallback);
        $preparedCallback['operationId'] = $this->getPurchaseOperationIdFromCallbackData($preparedCallback);
        $preparedCallback = $this->convertDataService->camelToSnakeArrayKeys($preparedCallback);
        $preparedCallback['transaction_type'] = $this->getPurchaseTransactionTypeFromCallbackData($preparedCallback);
        $preparedCallback['callback_data'] = $jsonPreparedCallback;
        $preparedCallback['callback_returned'] = true;
        $preparedCallback['updated_at'] = $this->dateTimeProvider->getNowDate();

        $conversionRate = $this->getConversionRateFromCallbackData($preparedCallback);
        if ($conversionRate !== null) {
            $preparedCallback['conversion_rate'] = $conversionRate;
        }

        try {
            $allianceOrderObject->bindChecknStoreAllianceOrder($preparedCallback);
        } catch (Exception $exception) {
            Log::add($exception->getMessage(), Log::ERROR);

            return false;
        }

        return true;
    }

    /**
     * @param array $refundData
     *
     * @return bool
     *
     * @since version 1.0.2
     */
    public function updateAllianceRefund(array $refundData): bool
    {
        $preparedRefundData = $this->convertDataService->camelToSnakeArrayKeys($refundData);

        if (!empty($preparedRefundData['merchant_request_id'])) {
            try {
                $allianceRefund = $this->allianceRefundTable->load(
                    $preparedRefundData['merchant_request_id'],
                    'merchant_request_id'
                );
                $allianceRefund->bindChecknStoreAllianceRefund($preparedRefundData);
            } catch (Exception $exception) {
                Log::add($exception->getMessage(), Log::ERROR);

                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * @param array $orderCallBackData
     * @param array $callbackData
     *
     * @return array
     *
     * @since version 1.0.0
     */
    private function prepareCallbackData(array $orderCallBackData, array $callbackData): array
    {
        $callbackData = $this->prepareOperations($callbackData);

        if (!empty($orderCallBackData)) {
            $operations = [];
            foreach ($callbackData['operations'] as $operation) {
                if (isset($operation['operationId'])) {
                    $isAlredyExist = $this->checkIfAlreadyExistOperation(
                        'operationId',
                        $operation['operationId'],
                        $orderCallBackData['operations']
                    );
                    if (!$isAlredyExist) {
                        $operations[] = $operation;
                    }
                }
                if (!isset($operation['operationId']) && $operation['status'] === 'FAIL') {
                    $isAlredyExistByRequestId = $this->checkIfAlreadyExistOperation(
                        'merchantRequestId',
                        $operation['merchantRequestId'],
                        $orderCallBackData['operations']
                    );
                    if (!$isAlredyExistByRequestId) {
                        $operation['operationId'] = '';
                        $operations[] = $operation;
                    }
                }
            }
            $callbackData['operations'] = array_merge($orderCallBackData['operations'], $operations);
        }

        return $callbackData;
    }

    /**
     * @param $callbackData
     *
     * @return array|mixed
     *
     * @since version 1.0.0
     */
    private function prepareOperations($callbackData)
    {
        if (isset($callbackData['operations'])) {
            return $callbackData;
        } elseif (isset($callbackData['operation'])) {
            $callbackData['operations'][] = $callbackData['operation'];
            unset($callbackData['operation']);
        }

        return $callbackData;
    }

    /**
     * @param string $field
     * @param string $value
     * @param array $callbackOperations
     *
     * @return bool
     *
     * @since version 1.0.2
     */
    private function checkIfAlreadyExistOperation( string $field,string $value, array $callbackOperations): bool
    {
        foreach ($callbackOperations as $callbackOperation) {
            if ($callbackOperation[$field] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $callbackData
     *
     * @return string
     *
     * @since version 1.0.0
     */
    private function getPurchaseOperationIdFromCallbackData($callbackData): string
    {
        $operationId = '';
        $purchaseOperation = $this->getPurchaseOperationFromCallbackData($callbackData);

        if (!empty($purchaseOperation['type']) && !empty($purchaseOperation['operationId'])) {
            $operationId = $purchaseOperation['operationId'];
        }

        return $operationId;
    }

    /**
     * @param $callbackData
     *
     * @return array
     *
     * @since version 1.2.0
     */
    private function getPurchaseOperationFromCallbackData($callbackData): array
    {
        $purchaseOperation = [];
        $operationTypes = [
            Config::OPERATION_TYPE_PURCHASE,
            Config::OPERATION_TYPE_A2A,
            Config::OPERATION_TYPE_PREAUTH,
            Config::OPERATION_TYPE_COMPLETION
        ];

        foreach ($callbackData['operations'] as $operation) {
            if (isset($operation['type'])
                && in_array($operation['type'], $operationTypes)
            ) {
                $purchaseOperation = $operation;
            }
        }

        return $purchaseOperation;
    }

    /**
     * @param array $callbackData  Snake-cased callback data (with 'operations' key)
     *
     * @return float|null
     *
     * @since version 1.4.0
     */
    private function getConversionRateFromCallbackData(array $callbackData): ?float
    {
        $targetTypes = [
            Config::OPERATION_TYPE_PREAUTH,
            Config::OPERATION_TYPE_PURCHASE
        ];

        $operations = $callbackData['operations'] ?? [];

        $conversionRate = null;
        foreach ($operations as $operation) {
            $type = $operation['type'] ?? $operation['type'] ?? '';
            if (in_array($type, $targetTypes, true)) {
                $rate = $operation['conversionRate'] ?? $operation['conversion_rate'] ?? null;
                if ($rate !== null && (float) $rate > 0) {
                    $conversionRate = (float) $rate;
                }
            }
        }

        return $conversionRate;
    }

    /**
     * @param $callbackData
     *
     * @return int|null
     *
     * @since version 1.2.0
     */
    private function getPurchaseTransactionTypeFromCallbackData($callbackData): ?int
    {
        $transactionType = null;
        $purchaseOperation = $this->getPurchaseOperationFromCallbackData($callbackData);

        if (!empty($purchaseOperation['type']) && !empty($purchaseOperation['transactionType'])) {
            $transactionType = $purchaseOperation['transactionType'];
        }

        return $transactionType;
    }
}