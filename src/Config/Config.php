<?php
namespace Alliance\Plugin\Vmpayment\Alliancepay\Config;

use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceAuthenticationData;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Exception;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\ConfigParser\PipeParser;
use Throwable;

class Config
{
    public const PAYMENT_METHODS = ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'];
    public const HPP_PAY_TYPE = 'PURCHASE';
    public const SENSITIVE_DATA_FIELD_REFRESH_TOKEN = 'refreshToken';
    public const SENSITIVE_DATA_FIELD_AUTH_TOKEN = 'authToken';
    public const SENSITIVE_DATA_FIELD_DEVICE_ID = 'deviceId';
    public const SENSITIVE_DATA_FIELD_SERVER_PUBLIC = 'serverPublic';
    public const SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME = 'tokenExpirationDateTime';
    public const SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION = 'tokenExpiration';
    public const SENSITIVE_DATA_FIELD_SESSION_EXPIRATION = 'sessionExpiration';
    public const SENSITIVE_DATA_FIELDS = [
        self::SENSITIVE_DATA_FIELD_REFRESH_TOKEN,
        self::SENSITIVE_DATA_FIELD_AUTH_TOKEN,
        self::SENSITIVE_DATA_FIELD_DEVICE_ID,
        self::SENSITIVE_DATA_FIELD_SERVER_PUBLIC,
        self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME,
        self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION,
        self::SENSITIVE_DATA_FIELD_SESSION_EXPIRATION,
    ];

    const PAYMENT_SERVICE_CODE_CONFIG_NAME = 'service_code';
    const PAYMENT_API_URL_CONFIG_NAME = 'api_url';
    const PAYMENT_AUTH_KEY_CONFIG_NAME = 'auth_key';

    const PAYMENT_MERCHAT_ID_CONFIG_NAME = 'merchant_id';

    const PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME = 'status_page_type';
    const PAYMENT_REFRESH_TOKEN_CONFIG_NAME = 'refresh_token';

    const PAYMENT_AUTH_TOKEN_CONFIG_NAME = 'auth_token';

    const PAYMENT_DEVICE_ID_CONFIG_NAME = 'device_id';

    const PAYMENT_SERVER_PUBLIC_KEY_CONFIG_NAME = 'server_public';

    const PAYMENT_TOKEN_EXPIRATION_DATE_TIME_CONFIG_NAME = 'token_expiration_date_time';

    const PAYMENT_TOKEN_EXPIRATION_CONFIG_NAME = 'token_expiration';

    const PAYMENT_SESSION_EXPIRATION_CONFIG_NAME = 'session_expiration';

    const PAYMENT_SUCCESS_PAYMENT_STATUS_CONFIG_NAME = 'status_payment_success';
    const PAYMENT_FAIL_PAYMENT_STATUS_CONFIG_NAME = 'status_payment_fail';

    const PAYMENT_SUCCESS_REFUND_STATUS_CONFIG_NAME = 'status_refund_success';
    const PAYMENT_FAIL_REFUND_STATUS_CONFIG_NAME = 'status_refund_fail';

    private array $params;

    private ?int $pluginId;

    private DatabaseInterface $db;

    private const PLUGIN_NAME = 'alliancepay';
    private const PLUGIN_SCOPE = 'vmpayment';

    public function __construct(
        private readonly DateTimeNormalizer $dateTimeNormalizer,
        private readonly AllianceAuthenticationData $allianceAuthenticationData,
        private readonly ConvertDataService $convertDataService,
    )
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        $plugin = PluginHelper::getPlugin(self::PLUGIN_SCOPE, self::PLUGIN_NAME);

        if ($plugin) {
            $this->pluginId = $plugin->id;
            $query = $this->db->getQuery(true)
                ->select('payment_params')
                ->from('#__virtuemart_paymentmethods')
                ->where('payment_jplugin_id = ' . $this->pluginId);

            $this->db->setQuery($query);
            $result = $this->db->loadResult() ?? '';
            $this->params = PipeParser::decode($result);
            $config = $this->allianceAuthenticationData->load(
                $this->pluginId,
                'virtuemart_paymentmethod_id'
            );
            if (!empty($config->getProperties())) {
                $this->params = [...$this->params, ...$config->getProperties()];
            }
        }

        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param string $key
     * @param $default
     *
     * @return mixed|null
     *
     * @since version 1.0.0
     */
    public function get(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     *
     * @return array
     *
     * @since version 1.0.0
     */
    public function getAll(): array
    {
        return $this->params;
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getServiceCode(): string
    {
        return $this->params[self::PAYMENT_SERVICE_CODE_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getApiUrl(): string
    {
        $url = $this->params[self::PAYMENT_API_URL_CONFIG_NAME] ?? '';

        if (!empty($url)) {
            $url = str_replace('\\', '', $url);
        }

        return $url ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getDeviceId(): string
    {
        return $this->params[self::PAYMENT_DEVICE_ID_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getRefreshToken(): string
    {
        return $this->params[self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getAuthorizationKey(): string
    {
        $key = $this->params[self::PAYMENT_AUTH_KEY_CONFIG_NAME] ?? '';

        if (!empty($key)) {
            $key = str_replace('\\', '', $key);
        }

        return $key ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getMerchantId(): string
    {
        return $this->params[self::PAYMENT_MERCHAT_ID_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getStatusPageType(): string
    {
        return $this->params[self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getSuccessPaymentStatus(): string
    {
        return $this->params[self::PAYMENT_SUCCESS_PAYMENT_STATUS_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getFailPaymentStatus(): string
    {
        return $this->params[self::PAYMENT_FAIL_PAYMENT_STATUS_CONFIG_NAME] ?? '';
    }

    public function getSuccessRefundStatus(): string
    {
        return $this->params[self::PAYMENT_SUCCESS_REFUND_STATUS_CONFIG_NAME] ?? '';
    }

    public function getFailRefundStatus(): string
    {
        return $this->params[self::PAYMENT_FAIL_REFUND_STATUS_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getServerPublicKey(): string
    {
        return $this->params[self::PAYMENT_SERVER_PUBLIC_KEY_CONFIG_NAME] ?? '';
    }

    /**
     * @param array $authData
     *
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function saveAuthentificationData(array $authData): void
    {
        if (!$this->hasAllRequiredFields($authData)) {
            return;
        }

        $authData[self::SENSITIVE_DATA_FIELD_SERVER_PUBLIC] =
            json_encode($authData[self::SENSITIVE_DATA_FIELD_SERVER_PUBLIC]);
        $authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME] = $this->dateTimeNormalizer->normalizeDate($authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME]);
        $authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION] = $this->dateTimeNormalizer->normalizeDate($authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION]);
        $authData[self::SENSITIVE_DATA_FIELD_SESSION_EXPIRATION] = $this->dateTimeNormalizer->normalizeDate($authData[self::SENSITIVE_DATA_FIELD_SESSION_EXPIRATION]);

        $convertedAuthData = $this->convertDataService->camelToSnakeArrayKeys($authData);
        $convertedAuthData['virtuemart_paymentmethod_id'] = $this->pluginId;

        try {
            $this->allianceAuthenticationData->reset();
            $this->allianceAuthenticationData->load(
                $this->pluginId,
                'virtuemart_paymentmethod_id'
            );
            if (!$this->allianceAuthenticationData->bind($convertedAuthData)) {
                throw new Exception(
                    'Помилка прив’язки даних: ' . $this->allianceAuthenticationData->getError()
                );
            }

            if (!$this->allianceAuthenticationData->check()) {
                throw new Exception(
                    'Помилка валідації: ' . $this->allianceAuthenticationData->getError()
                );
            }

            if (!$this->allianceAuthenticationData->store()) {
                throw new Exception(
                    'Помилка збереження в БД: ' . $this->allianceAuthenticationData->getError()
                );
            }
        } catch (Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR);
        }
    }

    /**
     * @param array $authData
     *
     * @return bool
     *
     * @throws Exception
     * @since version 1.0.0
     */
    private function hasAllRequiredFields(array $authData): bool
    {
        foreach (self::SENSITIVE_DATA_FIELDS as $key) {
            if (empty($authData[$key])) {
                throw new Exception('Alliance payment configuration key ' . $key . ' is missing.');
            }
        }

        return true;
    }
}
