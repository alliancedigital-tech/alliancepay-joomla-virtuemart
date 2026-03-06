<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\JweEncryptionService;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Cassandra\Exception\AlreadyExistsException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Ring\Future\FutureInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;

/**
 * Class HttpClient.
 */
class HttpClient
{
    private const METHOD_POST = 'POST';

    private const RESPONSE_VALIDATION_ERROR_TYPE = 'VALIDATION_ERROR';
    private const RESPONSE_ERROR_TYPE = 'ERROR';

    private const REQUEST_CONTENT_TYPE_TEXT = 'text/plain';

    private const REQUEST_CONTENT_TYPE_JSON = 'application/json';

    private const X_API_VERSION = 'V1';
    private const ENDPOINT_CREATE_ORDER = '/ecom/execute_request/hpp/v1/create-order';
    private const ENDPOINT_OPERATIONS = '/ecom/execute_request/hpp/v1/operations';
    private const ENDPOINT_REFUND = '/ecom/execute_request/payments/v3/refund';
    private const ENDPOINT_AUTHORIZE = '/api-gateway/authorize_virtual_device';
    private const MAX_AUTH_ATTEMPTS = 3;
    private $authCounter;


    public function __construct(
        private readonly HttpClientFactory $httpClientFactory,
        private readonly HttpFactory $httpFactory,
        private readonly JweEncryptionService $jweEncryptionService,
        private readonly Config $config
    ) {
        $this->authCounter = 0;
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param string $serviceCode
     *
     * @return array
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    public function authorize(string $serviceCode): array
    {
        $data = [
            'serviceCode' => $serviceCode,
        ];

        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_AUTHORIZE,
                $data
            );
        } catch (RequestException $e) {
            Log::add('Authorization failed: ' . $e->getMessage(), Log::ERROR);
            return [];
        }

        $responseContent = json_decode($response->getBody()->getContents(), true);

        return $this->validateResponse($responseContent);
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
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_CREATE_ORDER,
                $orderData
            );

            $responseContent = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::add('Create order failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Payment failed. Please try again later.',
            ];
        }

        return $this->validateResponse($responseContent);
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
        $serverPublicKey = json_decode($this->config->getServerPublicKey(), true);
        $encryptedRefundData = $this->jweEncryptionService->encrypt(
            $refundData,
            $serverPublicKey
        );

        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_REFUND,
                $encryptedRefundData,
                self::REQUEST_CONTENT_TYPE_TEXT
            );
        } catch (RequestException $e) {
            Log::add('Refund failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ];
        }

        $decodedResponse = json_decode($response->getBody()->getContents(), true);

        if (isset($decodedResponse['jwe'])) {
            $decryptedResponse = $this->jweEncryptionService->decrypt(
                $this->config->getAuthorizationKey(),
                $decodedResponse['jwe']
            );

            if (!empty($decryptedResponse)) {
                return $this->validateResponse($decryptedResponse);
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to refund order.',
        ];
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
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_OPERATIONS,
                ['hppOrderId' => $hppOrderId]
            );
            $responseContent = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::add('Get operation status failed: ' . $e->getMessage(), Log::ERROR);

            return [
                'success' => false,
                'message' => 'Get operation status failed',
            ];
        }

        return $this->validateResponse($responseContent);
    }

    /**
     * @param $method
     * @param $endpoint
     * @param $data
     * @param $contentType
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     * @since version 1.0.0
     */
    private function sendRequest(
        $method,
        $endpoint,
        $data = null,
        $contentType = self::REQUEST_CONTENT_TYPE_JSON
    ) {
        $baseUrl = $this->config->getApiUrl();

        $options = [
            'headers' => [
                'x-api_version' => self::X_API_VERSION,
                'x-device_id' => $this->config->getDeviceId(),
                'x-refresh_token' => $this->config->getRefreshToken(),
                'x-request_id' => uniqid(),
                'Content-Type' => $contentType
            ]
        ];

        if ($contentType === self::REQUEST_CONTENT_TYPE_TEXT) {
            $options['body'] = $data;
        }

        if ($data && $contentType === self::REQUEST_CONTENT_TYPE_JSON) {
            $options['json'] = $data;
            $options['headers']['Accept'] = $contentType;
        }

        try {
            $client = $this->httpClientFactory->create($baseUrl);

            return $client->send(
                $this->httpFactory->createRequest($method, $baseUrl . $endpoint),
                $options
            );
        } catch (Exception $e) {
            Log::add('Request failed: ' . $e->getMessage(), Log::ERROR);

            if ($e->getCode() === 401) {
                $reAuthResult = $this->errorAuthorizationHandler($e);
                if (!empty($reAuthResult)) {
                    $options['headers']['x-device-id'] = $reAuthResult['deviceId'];
                    $options['headers']['x-refresh_token'] = $reAuthResult['refreshToken'];

                    $client = $this->httpClientFactory->create($baseUrl);

                    return $client->send(
                        $this->httpFactory->createRequest($method, $baseUrl . $endpoint),
                        $options
                    );
                }
            } elseif ($e->getCode() === 0) {
                throw $e;
            }

            return $e->getResponse();
        }
    }

    /**
     * @param RequestException $e
     *
     * @return bool
     *
     * @throws Exception
     * @since version 1.0.0
     */
    private function errorAuthorizationHandler(RequestException $e): array
    {
        if ($e->getCode() === 401 && self::MAX_AUTH_ATTEMPTS >= $this->authCounter) {
            $msgCodes = ['b_expired_token', 'b_used_token', 'b_auth_token_expired'];
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            if (in_array($response['msgCode'], $msgCodes)) {
                $this->authCounter++;
                $result = $this->authorize($this->config->getServiceCode());
                if (!empty($result['jwe'])) {
                    $decryptResult = $this->jweEncryptionService->decrypt(
                        $this->config->getAuthorizationKey(),
                        $result['jwe']
                    );

                    if (!empty($decryptResult['refreshToken'])
                        && !empty($decryptResult['authToken'])
                        && !empty($decryptResult['deviceId'])
                        && !empty($decryptResult['serverPublic'])
                        && !empty($decryptResult['tokenExpirationDateTime'])
                        && !empty($decryptResult['tokenExpiration'])
                        && !empty($decryptResult['sessionExpiration'])
                    ) {
                        try {
                            $this->config->saveAuthentificationData($decryptResult);
                        } catch (AlreadyExistsException $e) {
                            Log::add($e->getMessage(), Log::ERROR);
                        }

                        return $decryptResult;
                    }
                }
            }
        }

        return [];
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
