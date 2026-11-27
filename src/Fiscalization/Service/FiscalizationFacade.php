<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Callback\FiscalizationCallbackProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationRowBuilder;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalizationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\FiscalOperationRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * The entry points of the payment plugin into fiscalization: callback, automatic sending after a
 * successful payment / refund, and the order-page block with its buttons. Keeps the plugin class thin.
 *
 * @since version 1.6.0
 */
class FiscalizationFacade
{
    public const ACTION_FISCALIZE = 'fiscalize';
    public const ACTION_REFRESH = 'refresh';

    private const LOG_CATEGORY = 'alliance_payment';

    private ?string $blockError = null;

    /** @var array<string, true> operations already scheduled in this request */
    private array $scheduled = [];

    public function __construct(
        private readonly Config $config,
        private readonly JwsVerifier $jwsVerifier,
        private readonly FiscalizationCallbackProcessor $callbackProcessor,
        private readonly FiscalizationService $service,
        private readonly FiscalizationRepository $records,
        private readonly FiscalOperationRepository $operations,
        private readonly FiscalizationRowBuilder $rowBuilder
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isFiscalizationEnabled();
    }

    /**
     * Verifies and processes a fiscalization callback body. The signature is mandatory.
     *
     * @return int HTTP status to answer with
     */
    public function handleCallback(string $rawBody): int
    {
        $token = trim($rawBody);

        if ($token === '' || str_contains($token, ' ') || substr_count($token, '.') !== 2) {
            Log::add('Fiscalization callback rejected: the body is not a signed token.', Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationCallbackProcessor::HTTP_BAD_REQUEST;
        }

        try {
            $payload = $this->jwsVerifier->verify($token);
        } catch (Throwable $e) {
            Log::add('Fiscalization callback rejected: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationCallbackProcessor::HTTP_BAD_REQUEST;
        }

        return $this->callbackProcessor->process($payload);
    }

    /**
     * Sends the operation for fiscalization after the response to the bank has been delivered, when
     * automatic fiscalization is on. A failure here never reaches the payment / refund flow.
     */
    public function scheduleAutomatic(int $orderId, string $operationId): void
    {
        if ($orderId <= 0 || $operationId === '' || !$this->config->isAutoFiscalizationEnabled()) {
            return;
        }

        $key = $orderId . ':' . $operationId;

        if (isset($this->scheduled[$key])) {
            return;
        }

        $this->scheduled[$key] = true;

        register_shutdown_function(function () use ($orderId, $operationId): void {
            try {
                ignore_user_abort(true);

                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                $this->service->fiscalize($orderId, $operationId);
            } catch (Throwable $e) {
                Log::add('Automatic fiscalization of ' . $operationId . ' failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>> rows of the order-page block (see FiscalizationRowBuilder)
     */
    public function blockRows(int $orderId): array
    {
        $this->blockError = null;
        $operations = [];
        $records = [];

        try {
            $operations = $this->operations->listForOrder($orderId);
        } catch (Throwable $e) {
            $this->failBlock($orderId, 'operations', $e);
        }

        try {
            $records = $this->records->findByOrderId($orderId);
        } catch (Throwable $e) {
            $this->failBlock($orderId, 'records', $e);
        }

        try {
            return $this->rowBuilder->build($operations, $records);
        } catch (Throwable $e) {
            $this->failBlock($orderId, 'rows', $e);

            return [];
        }
    }

    /**
     * @return string|null
     */
    public function blockError(): ?string
    {
        return $this->blockError;
    }

    /**
     * @param int $orderId
     * @param string $stage
     * @param Throwable $e
     * @return void
     */
    private function failBlock(int $orderId, string $stage, Throwable $e): void
    {
        $this->blockError = $stage . ': ' . $e->getMessage();
        Log::add('Fiscalization block for order ' . $orderId . ' failed at ' . $stage . ': ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);
    }

    public function perform(string $action, int $orderId, string $operationId): FiscalizationOutcome
    {
        return $action === self::ACTION_REFRESH
            ? $this->service->refreshStatus($orderId, $operationId)
            : $this->service->fiscalize($orderId, $operationId);
    }
}
