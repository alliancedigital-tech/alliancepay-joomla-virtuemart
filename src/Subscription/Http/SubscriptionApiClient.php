<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsSigner;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\HttpFactory;
use Joomla\CMS\Log\Log;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http
 *
 * @since version 1.5.0
 */
class SubscriptionApiClient
{
    private const METHOD_POST = 'POST';
    private const LOG_CATEGORY = 'alliance_payment';

    private const CONTENT_TYPE_JWS = 'application/jws';
    private const X_API_VERSION = 'V1';

    private const MSG_TYPE_VALIDATION_ERROR = 'VALIDATION_ERROR';
    private const MSG_TYPE_ERROR = 'ERROR';

    public const ENDPOINT_PLAN_CREATE = '/ecom/jws/scheduled_payments/plan/create_v1';
    public const ENDPOINT_PLAN_UPDATE = '/ecom/jws/scheduled_payments/plan/update_v1';
    public const ENDPOINT_PLAN_STATUS_UPDATE = '/ecom/jws/scheduled_payments/plan/status_update_v1';
    public const ENDPOINT_PLAN_VERSION_UPDATE = '/ecom/jws/scheduled_payments/plan/version/update_v1';
    public const ENDPOINT_VERSION_DELETE = '/ecom/jws/scheduled_payments/version/delete_v1';
    public const ENDPOINT_SUBSCRIPTION_ORDER_CREATE = '/ecom/jws/scheduled_payments/subscription-orders/create_v1';
    public const ENDPOINT_SUBSCRIPTION_ORDER_CANCEL = '/ecom/jws/scheduled_payments/subscription-orders/cancel_v1';
    public const ENDPOINT_RECURRING_PAYMENT = '/ecom/jws/scheduled_payments/payments/recurring_subscription_v1';
    public const ENDPOINT_ORDER_OPERATIONS = '/ecom/jws/payments/hpp/operations_v1';

    public function __construct(
        private readonly HttpClientFactory $httpClientFactory,
        private readonly HttpFactory $httpFactory,
        private readonly JwsSigner $jwsSigner,
        private readonly JwsVerifier $jwsVerifier,
        private readonly Config $config
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            [self::LOG_CATEGORY]
        );
    }

    /**
     * @param array $plan
     * @return array
     */
    public function createPlan(array $plan): array
    {
        return $this->call(self::ENDPOINT_PLAN_CREATE, $plan);
    }

    /**
     * @throws ProviderApiException
     */
    public function updatePlan(array $plan): array
    {
        return $this->call(self::ENDPOINT_PLAN_UPDATE, $plan);
    }

    /**
     * @throws ProviderApiException
     */
    public function updatePlanStatus(array $data): array
    {
        return $this->call(self::ENDPOINT_PLAN_STATUS_UPDATE, $data);
    }

    /**
     * @param string $planId
     * @param string $versionId
     * @return array
     */
    public function setInfiniteVersion(string $planId, string $versionId): array
    {
        return $this->call(
            self::ENDPOINT_PLAN_VERSION_UPDATE,
            ['planId' => $planId, 'versionId' => $versionId, 'infiniteVersion' => true]
        );
    }

    /**
     * @throws ProviderApiException
     */
    public function deleteVersion(array $data): array
    {
        return $this->call(self::ENDPOINT_VERSION_DELETE, $data);
    }

    /**
     * @param array $order
     * @return array
     */
    public function createSubscriptionOrder(array $order): array
    {
        return $this->call(self::ENDPOINT_SUBSCRIPTION_ORDER_CREATE, $order);
    }

    /**
     * @throws ProviderApiException
     */
    public function cancelSubscriptionOrder(array $data): array
    {
        return $this->call(self::ENDPOINT_SUBSCRIPTION_ORDER_CANCEL, $data);
    }

    /**
     * @param array $data
     * @return array
     */
    public function triggerRecurringPayment(array $data): array
    {
        return $this->call(self::ENDPOINT_RECURRING_PAYMENT, $data);
    }

    /**
     * @param string $hppOrderId
     * @return array[]
     */
    public function getOrderOperations(string $hppOrderId): array
    {
        $response = $this->call(
            self::ENDPOINT_ORDER_OPERATIONS,
            ['hppOrderId' => $hppOrderId],
            false
        );

        return self::normalizeOperations($response);
    }

    /**
     * @param array $response
     * @return array|array[]
     */
    public static function normalizeOperations(array $response): array
    {
        if (isset($response['operations']) && is_array($response['operations'])) {
            return array_values($response['operations']);
        }

        if (isset($response['operation']) && is_array($response['operation'])) {
            return [$response['operation']];
        }

        return [];
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @param bool $scheduledGroup true for scheduled_payments/*, false for the one-off payments group
     *
     * @return array
     *
     * @throws ProviderApiException
     */
    private function call(string $endpoint, array $data, bool $scheduledGroup = true): array
    {
        $privateKey = $this->config->getJwsPrivateKey();
        $kid = $this->config->getJwsKeyId();

        if ($privateKey === '' || $kid === '') {
            throw new ProviderApiException(
                'AlliancePay: JWS Private Key and Key ID are not configured; check plugin settings.'
            );
        }

        $baseUrl = $this->config->getApiUrl();

        if ($baseUrl === '') {
            throw new ProviderApiException('AlliancePay: API URL is not configured; check plugin settings.');
        }

        try {
            $jws = $this->jwsSigner->sign($privateKey, $kid, $endpoint, $data);
        } catch (Throwable $e) {
            throw new ProviderApiException($e->getMessage(), 0, null, [], '', $e);
        }

        try {
            $client = $this->httpClientFactory->create($baseUrl);
            $response = $client->send(
                $this->httpFactory->createRequest(self::METHOD_POST, $baseUrl . $endpoint),
                [
                    'body' => $jws,
                    'headers' => $this->buildHeaders($scheduledGroup),
                ]
            );
            $rawBody = (string) $response->getBody();
        } catch (RequestException $e) {
            $this->throwFromRequestException($endpoint, $e);
        } catch (ConnectException $e) {
            Log::add('Subscription API ' . $endpoint . ' connection failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            throw new ProviderApiException('AlliancePay: cannot reach the API (' . $e->getMessage() . ')', 0, null, [], '', $e);
        } catch (Throwable $e) {
            Log::add('Subscription API ' . $endpoint . ' failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            throw new ProviderApiException('AlliancePay: request failed (' . $e->getMessage() . ')', 0, null, [], '', $e);
        }

        return $this->parseAndValidate($endpoint, $rawBody);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(bool $scheduledGroup): array
    {
        $headers = [
            'Accept' => 'application/json',
            'x-request_id' => GenerateRequestIdentification::generateRequestId(),
        ];

        if ($scheduledGroup) {
            $headers['Content-Type'] = $this->config->getSubscriptionContentType();

            if ($this->config->isSubscriptionApiVersionHeaderSent()) {
                $headers['x-api_version'] = self::X_API_VERSION;
            }
        } else {
            $headers['Content-Type'] = self::CONTENT_TYPE_JWS;
            $headers['x-api_version'] = self::X_API_VERSION;
        }

        return $headers;
    }

    /**
     * @throws ProviderApiException
     */
    private function throwFromRequestException(string $endpoint, RequestException $e): never
    {
        $response = $e->getResponse();
        $status = $response ? $response->getStatusCode() : 0;
        $rawBody = $response ? (string) $response->getBody() : '';

        Log::add(
            'Subscription API ' . $endpoint . ' failed with HTTP ' . $status . ': ' . $rawBody,
            Log::ERROR,
            self::LOG_CATEGORY
        );

        if ($status === 401) {
            throw new ProviderApiException(
                'AlliancePay: request unauthorized (HTTP 401); check JWS Private Key and Key ID settings.',
                401,
                null,
                [],
                $rawBody,
                $e
            );
        }

        $parsed = $this->tryParseBody($rawBody);
        $msgType = is_array($parsed) ? ($parsed['msgType'] ?? null) : null;
        $message = is_array($parsed) && !empty($parsed['msgText'])
            ? (string) $parsed['msgText']
            : 'AlliancePay: request failed with HTTP ' . $status;

        throw new ProviderApiException(
            $message,
            $status,
            is_string($msgType) ? $msgType : null,
            is_array($parsed) ? $this->extractValidationErrors($parsed) : [],
            $rawBody,
            $e
        );
    }

    /**
     * @throws ProviderApiException
     */
    private function parseAndValidate(string $endpoint, string $rawBody): array
    {
        try {
            $payload = $this->tryParseBody($rawBody, true);
        } catch (Throwable $e) {
            Log::add('Subscription API ' . $endpoint . ' response verification failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            throw new ProviderApiException(
                'AlliancePay: cannot verify the provider response (' . $e->getMessage() . ')',
                0,
                null,
                [],
                $rawBody,
                $e
            );
        }

        if (!is_array($payload)) {
            throw new ProviderApiException('AlliancePay: unexpected (non-JSON) response', 0, null, [], $rawBody);
        }

        if (isset($payload['msgType'])) {
            $msgType = (string) $payload['msgType'];

            if (in_array($msgType, [self::MSG_TYPE_VALIDATION_ERROR, self::MSG_TYPE_ERROR], true)) {
                Log::add(
                    'Subscription API ' . $endpoint . ' returned ' . $msgType . ': ' . $rawBody,
                    Log::ERROR,
                    self::LOG_CATEGORY
                );

                throw new ProviderApiException(
                    (string) ($payload['msgText'] ?? 'AlliancePay: the provider rejected the request'),
                    200,
                    $msgType,
                    $this->extractValidationErrors($payload),
                    $rawBody
                );
            }
        }

        return $payload;
    }

    /**
     * @param string $rawBody
     * @param bool $verifySigned
     * @return array|null
     */
    private function tryParseBody(string $rawBody, bool $verifySigned = false): ?array
    {
        $trimmed = trim($rawBody);

        if ($trimmed === '') {
            return null;
        }

        $isToken = !str_contains($trimmed, ' ') && substr_count($trimmed, '.') === 2;

        if ($isToken) {
            if (!$verifySigned) {
                return null;
            }

            return $this->jwsVerifier->verify($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return string[]
     */
    private function extractValidationErrors(array $payload): array
    {
        $messages = [];
        $errors = $payload['validation']['errors'] ?? [];

        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (is_array($error) && !empty($error['message'])) {
                    $field = !empty($error['field']) ? $error['field'] . ': ' : '';
                    $messages[] = $field . $error['message'];
                }
            }
        }

        return $messages;
    }
}
