<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsSigner;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\HttpFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use RuntimeException;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway
 *
 * @since version 1.0.0
 */
class HttpClient
{
    private const METHOD_POST = 'POST';

    private const RESPONSE_VALIDATION_ERROR_TYPE = 'VALIDATION_ERROR';
    private const RESPONSE_ERROR_TYPE = 'ERROR';
    private const CONTENT_TYPE_JWS = 'application/jws';
    private const X_API_VERSION = 'V1';
    private const ENDPOINT_CREATE_ORDER = '/ecom/jws/payments/hpp/create_order_v1';
    private const ENDPOINT_OPERATIONS = '/ecom/jws/payments/hpp/operations_v1';
    private const ENDPOINT_REFUND = '/ecom/jws/payments/refund_v1';
    private const ENDPOINT_COMPLETION = '/ecom/jws/payments/preauth/completion_v1';

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
            ['alliance_payment']
        );
    }

    /**
     * @param array $orderData
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function createOrder(array $orderData): array
    {
        try {
            return $this->signedRequest(self::ENDPOINT_CREATE_ORDER, $orderData);
        } catch (RequestException $e) {
            Log::add('Create order failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Payment failed. Please try again later.',
            ];
        }
    }

    /**
     * @param array $refundData
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function refund(array $refundData): array
    {
        try {
            return $this->signedRequest(self::ENDPOINT_REFUND, $refundData);
        } catch (RequestException $e) {
            Log::add('Refund failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array $completionData
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.3.0
     */
    public function completion(array $completionData): array
    {
        try {
            return $this->signedRequest(self::ENDPOINT_COMPLETION, $completionData);
        } catch (RequestException $e) {
            Log::add('Completion failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Completion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param string $hppOrderId
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function getOrderOperations(string $hppOrderId): array
    {
        try {
            return $this->signedRequest(self::ENDPOINT_OPERATIONS, ['hppOrderId' => $hppOrderId]);
        } catch (RequestException $e) {
            Log::add('Get operation status failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Get operation status failed',
            ];
        }
    }

    /**
     * @param string $endpoint
     * @param array $data
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.5.0
     */
    private function signedRequest(string $endpoint, array $data): array
    {
        $privateKey = $this->config->getJwsPrivateKey();
        $kid = $this->config->getJwsKeyId();

        if (empty($privateKey) || empty($kid)) {
            throw new RuntimeException(
                'AlliancePay: JWS Private Key and Key ID are not configured; check plugin settings.'
            );
        }

        $baseUrl = $this->config->getApiUrl();
        $jws = $this->jwsSigner->sign($privateKey, $kid, $endpoint, $data);

        $options = [
            'body' => $jws,
            'headers' => [
                'Content-Type' => self::CONTENT_TYPE_JWS,
                'Accept' => 'application/json',
                'x-api_version' => self::X_API_VERSION,
                'x-request_id' => GenerateRequestIdentification::generateRequestId(),
            ],
        ];

        try {
            $client = $this->httpClientFactory->create($baseUrl);
            $response = $client->send(
                $this->httpFactory->createRequest(self::METHOD_POST, $baseUrl . $endpoint),
                $options
            );
        } catch (RequestException $e) {
            if ($e->getCode() === 401) {
                throw new RequestException(
                    'AlliancePay: request unauthorized (HTTP 401); check JWS Private Key and Key ID settings.',
                    $e->getRequest(),
                    $e->getResponse(),
                    $e
                );
            }

            throw $e;
        }

        $responseBody = $this->parseResponseBody($response->getBody()->getContents());

        return $this->validateResponse($responseBody);
    }

    /**
     * @param string $body
     *
     * @return array
     *
     * @since version 1.5.0
     */
    private function parseResponseBody(string $body): array
    {
        $trimmed = trim($body);
        $isToken = $trimmed !== '' && !str_contains($trimmed, ' ');
        $dotCount = substr_count($trimmed, '.');

        if ($isToken && $dotCount === 2) {
            return $this->jwsVerifier->verify($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array $response
     *
     * @return array
     *
     * @since version 1.0.1
     */
    private function validateResponse(array $response): array
    {
        if (isset($response['msgType'])) {
            $app = Factory::getApplication();
            if ($response['msgType'] === self::RESPONSE_VALIDATION_ERROR_TYPE
                && !empty($response['validation'])
                && !empty($response['validation']['errors'])
                && is_array($response['validation']['errors'])
            ) {
                foreach ($response['validation']['errors'] as $error) {
                    $message = $error['message'] ?? '';
                    $app->enqueueMessage($message, 'error');
                }
            }
            if (!empty($response['msgText']) && $response['msgType'] === self::RESPONSE_ERROR_TYPE) {
                $app->enqueueMessage($response['msgText'], 'error');
            }

            $cartUrl = Route::_('index.php?option=com_virtuemart&view=cart', false);
            $app->redirect($cartUrl);
            $app->close();
        }

        return $response;
    }
}
