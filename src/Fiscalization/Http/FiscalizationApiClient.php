<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Http;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ApiErrorMapper;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalizationIssue;
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
 * POST {base_url}/ecom/jws/cashbox/v2/fiscalization/create, signed like the other Ecom calls.
 *
 * Never throws and never redirects: every failure (HTTP 400/401/5xx, timeout, bad signature) comes back as
 * a FiscalizationApiResult with an issue, so it can be stored in the fiscalization record and shown to the
 * administrator without breaking the payment or refund flow. Signatures and keys are not logged.
 *
 * @since version 1.6.0
 */
class FiscalizationApiClient
{
    public const ENDPOINT = '/ecom/jws/cashbox/v2/fiscalization/create';

    private const CONTENT_TYPE_JWS = 'application/jws';
    private const X_API_VERSION = 'V1';
    private const TIMEOUT_SECONDS = 30;
    private const LOG_CATEGORY = 'alliance_payment';

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

    public function create(array $request): FiscalizationApiResult
    {
        $privateKey = $this->config->getJwsPrivateKey();
        $kid = $this->config->getJwsKeyId();
        $baseUrl = $this->config->getApiUrl();

        if ($privateKey === '' || $kid === '' || $baseUrl === '') {
            return FiscalizationApiResult::failure(
                new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['API URL, JWS Private Key and Key ID must be configured'])
            );
        }

        try {
            $jws = $this->jwsSigner->sign($privateKey, $kid, self::ENDPOINT, $request);
        } catch (Throwable $e) {
            Log::add('Fiscalization: signing failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]));
        }

        Log::add(
            sprintf(
                'Fiscalization request: operationId=%s merchantRequestId=%s products=%d',
                $request['operationId'] ?? '',
                $request['merchantRequestId'] ?? '',
                count($request['products'] ?? [])
            ),
            Log::INFO,
            self::LOG_CATEGORY
        );

        try {
            $response = $this->httpClientFactory->create($baseUrl, self::TIMEOUT_SECONDS)->send(
                $this->httpFactory->createRequest('POST', $baseUrl . self::ENDPOINT),
                [
                    'body' => $jws,
                    'headers' => [
                        'Content-Type' => self::CONTENT_TYPE_JWS,
                        'Accept' => 'application/json',
                        'x-api_version' => self::X_API_VERSION,
                        'x-request_id' => GenerateRequestIdentification::generateRequestId(),
                    ],
                ]
            );
            $status = $response->getStatusCode();
            $rawBody = (string) $response->getBody();
        } catch (RequestException $e) {
            return $this->failureFromRequestException($e);
        } catch (ConnectException $e) {
            Log::add('Fiscalization: connection failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]));
        } catch (Throwable $e) {
            Log::add('Fiscalization: request failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]));
        }

        try {
            $body = $this->parseBody($rawBody);
        } catch (Throwable $e) {
            Log::add('Fiscalization: response verification failed: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationApiResult::failure(new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, [$e->getMessage()]), $status);
        }

        if ($body === null) {
            Log::add('Fiscalization: empty or unreadable response (HTTP ' . $status . ')', Log::ERROR, self::LOG_CATEGORY);

            return FiscalizationApiResult::failure(
                new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['unreadable response, HTTP ' . $status]),
                $status
            );
        }

        Log::add('Fiscalization response: ' . json_encode($body, JSON_UNESCAPED_UNICODE), Log::INFO, self::LOG_CATEGORY);

        return FiscalizationApiResult::success($body, $status);
    }

    private function failureFromRequestException(RequestException $e): FiscalizationApiResult
    {
        $response = $e->getResponse();
        $status = $response ? $response->getStatusCode() : 0;
        $rawBody = $response ? (string) $response->getBody() : '';

        Log::add('Fiscalization: HTTP ' . $status . ': ' . $rawBody, Log::ERROR, self::LOG_CATEGORY);

        if ($status === 401) {
            return FiscalizationApiResult::failure(
                new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['HTTP 401: check the JWS Private Key and Key ID settings']),
                $status
            );
        }

        if ($status === 400) {
            $parsed = null;

            try {
                $parsed = $this->parseBody($rawBody);
            } catch (Throwable) {
                // fall through to the generic error
            }

            return FiscalizationApiResult::failure(
                ApiErrorMapper::toIssue(
                    isset($parsed['msgCode']) ? (string) $parsed['msgCode'] : null,
                    $parsed['msgAttrs'] ?? null,
                    isset($parsed['msgText']) ? (string) $parsed['msgText'] : null
                ),
                $status
            );
        }

        return FiscalizationApiResult::failure(
            new FiscalizationIssue(FiscalizationIssue::API_UNAVAILABLE, ['HTTP ' . $status . ($status === 0 ? ' ' . $e->getMessage() : '')]),
            $status
        );
    }

    private function parseBody(string $rawBody): ?array
    {
        $trimmed = trim($rawBody);

        if ($trimmed === '') {
            return null;
        }

        if (!str_contains($trimmed, ' ') && substr_count($trimmed, '.') === 2) {
            return $this->jwsVerifier->verify($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
