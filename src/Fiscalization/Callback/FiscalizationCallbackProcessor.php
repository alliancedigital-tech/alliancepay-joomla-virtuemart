<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ParsedFiscalization;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalizationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service\FiscalizationRecordUpdater;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * Handles the verified payload of a fiscalization callback and tells which HTTP status to answer with:
 *  - 200: applied, ignored as stale / not lowering the status, or unknown operation (logged; answering
 *         with an error would only make the bank redeliver a callback we can never match);
 *  - 400: the payload cannot be used (no operationId / merchantRequestId, or no valid status);
 *  - 500: processing failed; a non-2xx lets the bank redeliver, and the update is idempotent.
 * Signature verification happens before this class; an invalid signature never reaches it.
 *
 * @since version 1.6.0
 */
class FiscalizationCallbackProcessor
{
    public const HTTP_OK = 200;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_ERROR = 500;

    private const LOG_CATEGORY = 'alliance_payment';

    public function __construct(
        private readonly FiscalizationRepository $records,
        private readonly FiscalizationRecordUpdater $updater
    ) {
    }

    public function process(array $payload): int
    {
        $parsed = ParsedFiscalization::fromArray($payload);

        if ($parsed->operationId === null && $parsed->merchantRequestId === null) {
            Log::add('Fiscalization callback without operationId and merchantRequestId.', Log::WARNING, self::LOG_CATEGORY);

            return self::HTTP_BAD_REQUEST;
        }

        if ($parsed->status === null) {
            Log::add('Fiscalization callback for ' . ($parsed->operationId ?? $parsed->merchantRequestId) . ' has no valid status.', Log::WARNING, self::LOG_CATEGORY);

            return self::HTTP_BAD_REQUEST;
        }

        try {
            $record = ($parsed->operationId !== null ? $this->records->findByOperationId($parsed->operationId) : null)
                ?? ($parsed->merchantRequestId !== null ? $this->records->findByMerchantRequestId($parsed->merchantRequestId) : null);

            if ($record === null) {
                Log::add(
                    'Fiscalization callback for an unknown operation (operationId=' . ($parsed->operationId ?? '-')
                    . ', merchantRequestId=' . ($parsed->merchantRequestId ?? '-') . '); ignored.',
                    Log::WARNING,
                    self::LOG_CATEGORY
                );

                return self::HTTP_OK;
            }

            $this->updater->applyParsed($record, $parsed, $payload, 'callback');
        } catch (Throwable $e) {
            Log::add('Fiscalization callback processing failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return self::HTTP_ERROR;
        }

        return self::HTTP_OK;
    }
}
