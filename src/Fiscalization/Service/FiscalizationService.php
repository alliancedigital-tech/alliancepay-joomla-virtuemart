<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationRequestBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStateMachine;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationStatus;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationValidationException;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperation;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalOperationType;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ProductsBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Http\FiscalizationApiClient;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalizationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalOperationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\OrderSnapshotRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Url\UrlProvider;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\DuplicateKeyException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * Sends PURCHASE / REFUND operations to fiscalization and keeps the local record in step.
 *
 *  - fiscalize(): allowed when there is no record yet or the last attempt ended in a local ERROR;
 *  - refreshStatus(): re-sends the stored request (same merchantRequestId, so Ecom answers idempotently)
 *    for a PENDING record or to re-check a FAIL;
 *  - applyParsed(): the single place where a synchronous response or a callback changes a record.
 *
 * It never throws into the payment / refund flow: every failure is stored in the record (status ERROR)
 * and returned as a FiscalizationOutcome.
 *
 * @since version 1.6.0
 */
class FiscalizationService implements FiscalizationRecordUpdater
{
    private const LOG_CATEGORY = 'alliance_payment';

    public function __construct(
        private readonly FiscalizationRepository $records,
        private readonly FiscalOperationRepository $operations,
        private readonly OrderSnapshotRepository $orders,
        private readonly ProductsBuilder $productsBuilder,
        private readonly FiscalizationRequestBuilder $requestBuilder,
        private readonly ProductFiscalAttributeService $attributes,
        private readonly FiscalizationApiClient $client,
        private readonly OrderHistoryNote $orderNote,
        private readonly UrlProvider $urlProvider,
        private readonly Config $config
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            [self::LOG_CATEGORY]
        );
    }

    public function fiscalize(int $orderId, string $operationId): FiscalizationOutcome
    {
        try {
            return $this->doFiscalize($orderId, $operationId);
        } catch (Throwable $e) {
            Log::add('Fiscalization of ' . $operationId . ' failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return new FiscalizationOutcome(false, FiscalizationStatus::Error, [$this->translate(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]))]);
        }
    }

    public function refreshStatus(int $orderId, string $operationId): FiscalizationOutcome
    {
        try {
            return $this->doRefresh($orderId, $operationId);
        } catch (Throwable $e) {
            Log::add('Fiscalization status refresh of ' . $operationId . ' failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return new FiscalizationOutcome(false, null, [$this->translate(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]))]);
        }
    }

    /**
     * Applies a synchronous response or a callback to a record, honouring the state machine, and adds an
     * order note when the record becomes SUCCESS or FAIL.
     *
     * @param array $record the stored row
     * @param array $payload the raw response / callback body
     * @param string $source 'response' or 'callback'
     *
     * @return array the row after the update (unchanged when the data was ignored)
     */
    public function applyParsed(array $record, ParsedFiscalization $parsed, array $payload, string $source): array
    {
        $payloadColumn = $source === 'callback' ? 'callback_payload' : 'response_payload';
        $encoded = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stored = FiscalizationStatus::tryFrom((string) $record['status']);
        $incoming = $parsed->status;

        if ($incoming === null) {
            Log::add('Fiscalization ' . $source . ' for ' . $record['operation_id'] . ' has no usable status; ignored.', Log::WARNING, self::LOG_CATEGORY);

            return $record;
        }

        if (!FiscalizationStateMachine::shouldApply($stored, $incoming, $record['modification_date_time'] ?? null, $parsed->modifiedAt())) {
            Log::add(
                'Fiscalization ' . $source . ' for ' . $record['operation_id'] . ' ignored (stored ' . ($stored?->value ?? '-') . ', incoming ' . $incoming->value . ').',
                Log::INFO,
                self::LOG_CATEGORY
            );
            $this->records->update((int) $record['id'], [$payloadColumn => $encoded]);

            return $this->records->findByOperationId((string) $record['operation_id']) ?? $record;
        }

        $fields = [
            'status' => $incoming->value,
            'bucket_id' => $parsed->bucketId,
            'fiscal_number' => $parsed->fiscalNumber,
            'receipt_url' => $parsed->receiptUrl,
            'fiscalization_date_time' => $parsed->fiscalizationDateTime,
            'fiscalization_modification_date_time' => $parsed->fiscalizationModificationDateTime,
            'modification_date_time' => $parsed->modifiedAt(),
            'error_code' => $incoming === FiscalizationStatus::Fail ? $parsed->errorCode : null,
            'error_description' => $incoming === FiscalizationStatus::Fail ? $parsed->errorDescription : null,
            $payloadColumn => $encoded,
        ];

        $this->records->update((int) $record['id'], $fields);
        $updated = $this->records->findByOperationId((string) $record['operation_id']) ?? $record;

        if ($stored !== $incoming) {
            $this->noteResult((int) $record['order_id'], $incoming, $parsed);
        }

        return $updated;
    }

    public function translate(FiscalizationIssue $issue): string
    {
        return Text::sprintf($issue->key, ...$issue->params);
    }

    private function doFiscalize(int $orderId, string $operationId): FiscalizationOutcome
    {
        if (!$this->config->isFiscalizationEnabled()) {
            return $this->rejected(FiscalizationIssue::NOT_ALLOWED);
        }

        $record = $this->records->findByOperationId($operationId);

        if ($record !== null && (int) $record['order_id'] !== $orderId) {
            return $this->rejected(FiscalizationIssue::OPERATION_NOT_FOUND);
        }

        if (!FiscalizationStatus::canFiscalize($record ? FiscalizationStatus::tryFrom((string) $record['status']) : null)) {
            return $this->rejected(FiscalizationIssue::NOT_ALLOWED, $record);
        }

        $operation = $this->operations->find($orderId, $operationId);

        if ($operation === null) {
            return $this->rejected(FiscalizationIssue::OPERATION_NOT_FOUND);
        }

        $merchantRequestId = $record['merchant_request_id'] ?? GenerateRequestIdentification::generateRequestId();

        try {
            $request = $this->buildRequest($operation, $merchantRequestId);
        } catch (FiscalizationValidationException $e) {
            $messages = array_map([$this, 'translate'], $e->issues);
            $record = $this->saveRecord($record, $operation, $merchantRequestId, '{}', FiscalizationStatus::Error, implode(' ', $messages));

            return new FiscalizationOutcome(false, FiscalizationStatus::Error, $messages, $record);
        }

        $payload = (string) json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Stored before sending: the merchantRequestId must survive a lost connection (ERROR allows a retry).
        $record = $this->saveRecord($record, $operation, $merchantRequestId, $payload, FiscalizationStatus::Error, Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_NOT_SENT'));

        return $this->send($record, $request);
    }

    private function doRefresh(int $orderId, string $operationId): FiscalizationOutcome
    {
        $record = $this->records->findByOperationId($operationId);

        if ($record === null || (int) $record['order_id'] !== $orderId) {
            return $this->rejected(FiscalizationIssue::OPERATION_NOT_FOUND);
        }

        if (!$this->config->isFiscalizationEnabled() || !FiscalizationStatus::canRefresh(FiscalizationStatus::tryFrom((string) $record['status']))) {
            return $this->rejected(FiscalizationIssue::NOT_ALLOWED, $record);
        }

        $request = json_decode((string) $record['request_payload'], true);

        if (!is_array($request) || empty($request['products'])) {
            return $this->rejected(FiscalizationIssue::NO_PRODUCTS, $record);
        }

        return $this->send($record, $request);
    }

    /**
     * @param array $record stored row
     * @param array $request request body to (re-)send
     */
    private function send(array $record, array $request): FiscalizationOutcome
    {
        $result = $this->client->create($request);

        if (!$result->isSuccess()) {
            $message = $this->translate($result->issue);
            $status = FiscalizationStatus::tryFrom((string) $record['status']);

            // A fresh attempt ends in ERROR with the reason. Re-checking a PENDING / FAIL record that
            // failed on the network leaves the record as it is: the earlier result still stands.
            if ($status === FiscalizationStatus::Error) {
                $this->records->update((int) $record['id'], ['error_description' => $message]);
            }

            return new FiscalizationOutcome(false, $status, [$message], $this->records->findByOperationId((string) $record['operation_id']));
        }

        $parsed = ParsedFiscalization::fromArray($result->body);

        if ($parsed->status === null) {
            $message = $this->translate(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['response without a valid status']));
            $this->records->update((int) $record['id'], ['status' => FiscalizationStatus::Error->value, 'error_description' => $message, 'response_payload' => (string) json_encode($result->body, JSON_UNESCAPED_UNICODE)]);

            return new FiscalizationOutcome(false, FiscalizationStatus::Error, [$message], $this->records->findByOperationId((string) $record['operation_id']));
        }

        $updated = $this->applyParsed($record, $parsed, $result->body, 'response');
        $status = FiscalizationStatus::tryFrom((string) $updated['status']);

        return new FiscalizationOutcome(
            $status !== FiscalizationStatus::Error,
            $status,
            $status === FiscalizationStatus::Fail ? [trim(($updated['error_code'] ?? '') . ' ' . ($updated['error_description'] ?? ''))] : [],
            $updated
        );
    }

    /**
     * @throws FiscalizationValidationException
     */
    private function buildRequest(FiscalOperation $operation, string $merchantRequestId): array
    {
        $order = $this->orders->load($operation->orderId);

        if ($order === null) {
            throw FiscalizationValidationException::single(FiscalizationIssue::ORDER_NOT_FOUND);
        }

        $shipment = Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_LINE_SHIPMENT');
        $payment = Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_LINE_PAYMENT');

        if ($operation->type === FiscalOperationType::Purchase) {
            $lines = $this->productsBuilder->buildPurchase($order, $operation->amountKop, $shipment, $payment);
            $comment = 'PAYMENT_FOR_ORDER_' . $order->orderNumber;
        } else {
            if ($operation->refundSelection === null) {
                throw FiscalizationValidationException::single(FiscalizationIssue::REFUND_ITEMS_MISSING);
            }

            $original = $operation->originalOperationId !== null
                ? $this->operations->find($operation->orderId, $operation->originalOperationId)
                : null;

            if ($original === null || $original->type !== FiscalOperationType::Purchase) {
                throw FiscalizationValidationException::single(FiscalizationIssue::ORIGINAL_NOT_FOUND);
            }

            $lines = $this->productsBuilder->buildRefund($order, $original->amountKop, $operation->refundSelection, $operation->amountKop, $shipment, $payment);
            $comment = 'REFUND_FOR_ORDER_' . $order->orderNumber;
        }

        return $this->requestBuilder->build(
            $merchantRequestId,
            $this->config->getMerchantId(),
            $operation->operationId,
            $comment,
            $this->urlProvider->getFiscalizationCallbackUrl($operation->orderId),
            $lines,
            $operation->amountKop,
            fn (int $productId) => $this->attributes->resolveForProduct($productId)
        );
    }

    /**
     * Inserts the record, or reuses the existing one (upsert by operation_id).
     */
    private function saveRecord(?array $record, FiscalOperation $operation, string $merchantRequestId, string $payload, FiscalizationStatus $status, string $description): array
    {
        $fields = [
            'status' => $status->value,
            'request_payload' => $payload,
            'error_description' => $description,
        ];

        if ($record !== null) {
            $this->records->update((int) $record['id'], $fields);

            return $this->records->findByOperationId($operation->operationId) ?? $record;
        }

        try {
            $this->records->insert([
                'order_id' => $operation->orderId,
                'operation_id' => $operation->operationId,
                'operation_type' => $operation->type->value,
                'merchant_request_id' => $merchantRequestId,
            ] + $fields);
        } catch (DuplicateKeyException) {
            // Another request created the record in the meantime; continue with it.
            $existing = $this->records->findByOperationId($operation->operationId);

            if ($existing !== null) {
                $this->records->update((int) $existing['id'], $fields);
            }
        }

        return $this->records->findByOperationId($operation->operationId);
    }

    private function noteResult(int $orderId, FiscalizationStatus $status, ParsedFiscalization $parsed): void
    {
        if ($status === FiscalizationStatus::Success) {
            $this->orderNote->add($orderId, Text::sprintf('VMPAYMENT_ALLIANCEPAY_FISCAL_NOTE_SUCCESS', (string) $parsed->fiscalNumber));
        } elseif ($status === FiscalizationStatus::Fail) {
            $this->orderNote->add($orderId, Text::sprintf('VMPAYMENT_ALLIANCEPAY_FISCAL_NOTE_FAIL', (string) $parsed->errorCode, (string) $parsed->errorDescription));
        }
    }

    private function rejected(string $key, ?array $record = null): FiscalizationOutcome
    {
        return new FiscalizationOutcome(
            false,
            $record ? FiscalizationStatus::tryFrom((string) $record['status']) : null,
            [$this->translate(new FiscalizationIssue($key))],
            $record
        );
    }
}
