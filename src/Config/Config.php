<?php
namespace Alliance\Plugin\Vmpayment\Alliancepay\Config;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Alliance\Plugin\Vmpayment\Alliancepay\Config\ConfigParser\PipeParser;

class Config
{
    public const PAYMENT_METHODS = ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'];
    public const HPP_PAY_TYPE_PURCHASE = 'PURCHASE';
    public const HPP_PAY_TYPE_A2A = 'A2A';
    public const HPP_PAY_TYPE_PREAUTH = 'PREAUTH';
    public const CURRENCY_CODE_MAP = [
        'UAH' => 980,
        'USD' => 840,
        'EUR' => 978,
    ];
    // Subscriptions are UAH-only, regardless of which currencies a one-off payment accepts
    // (RuleValidator::supportedCurrencyCodes(), ProductLookup's price lookup).
    public const SUBSCRIPTION_CURRENCY_CODE = 980;
    public const OPERATION_TYPE_A2A = 'ACCOUNT_2_ACCOUNT';
    public const DIRECT_TYPE_BANK_LINK = 'BANK_LINK';
    public const PRIORITY_BANK_CODE = 'ALL_BANKS';

    public const OPERATION_TYPE_PURCHASE = 'PURCHASE';
    public const OPERATION_TYPE_REFUND = 'REFUND';
    public const OPERATION_TYPE_ACCOUNT_2_ACCOUNT = 'ACCOUNT_2_ACCOUNT';
    public const OPERATION_TYPE_PREAUTH = 'PREAUTH';
    public const OPERATION_TYPE_COMPLETION = 'COMPLETION';

    const PAYMENT_API_URL_CONFIG_NAME = 'api_url';
    const PAYMENT_AUTH_KEY_CONFIG_NAME = 'auth_key';
    const PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME = 'jws_private_key';
    const PAYMENT_JWS_KEY_ID_CONFIG_NAME = 'jws_key_id';

    const PAYMENT_MERCHAT_ID_CONFIG_NAME = 'merchant_id';

    const PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME = 'status_page_type';
    const PAYMENT_HPP_PAY_TYPE_CONFIG_NAME = 'payment_type';

    const PAYMENT_SUCCESS_PAYMENT_STATUS_CONFIG_NAME = 'status_payment_success';
    const PAYMENT_FAIL_PAYMENT_STATUS_CONFIG_NAME = 'status_payment_fail';

    const PAYMENT_SUCCESS_REFUND_STATUS_CONFIG_NAME = 'status_refund_success';
    const PAYMENT_FAIL_REFUND_STATUS_CONFIG_NAME = 'status_refund_fail';

    const PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME = 'preauth_exp_date';
    const PAYMENT_PREAUTH_PENDING_STATUS_CONFIG_NAME = 'status_preauth_pending';
    const PAYMENT_COMPLETION_TRIGGER_STATUS_CONFIG_NAME = 'status_completion_trigger';

    const SUBSCRIPTIONS_ENABLED_CONFIG_NAME = 'subscriptions_enabled';
    const SUBSCRIPTION_REPRICE_MIN_DAYS_CONFIG_NAME = 'subscription_reprice_min_days';
    const SUBSCRIPTION_CONTENT_TYPE_CONFIG_NAME = 'subscription_content_type';
    const SUBSCRIPTION_SEND_API_VERSION_CONFIG_NAME = 'subscription_send_api_version';

    public const DEFAULT_SUBSCRIPTION_REPRICE_MIN_DAYS = 30;
    public const DEFAULT_SUBSCRIPTION_CONTENT_TYPE = 'application/jwt';

    private array $params = [];

    private ?int $pluginId;

    private DatabaseInterface $db;

    private const PLUGIN_NAME = 'alliancepay';
    private const PLUGIN_SCOPE = 'vmpayment';

    public function __construct()
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
     * @since version 1.5.0
     */
    public function getJwsPrivateKey(): string
    {
        $key = $this->params[self::PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME] ?? '';

        if (!empty($key)) {
            $key = str_replace('\\', '', $key);
        }

        return $key ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.5.0
     */
    public function getJwsKeyId(): string
    {
        return $this->params[self::PAYMENT_JWS_KEY_ID_CONFIG_NAME] ?? '';
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

    public function getPaymentType(): string
    {
        return $this->params[self::PAYMENT_HPP_PAY_TYPE_CONFIG_NAME] ?? self::HPP_PAY_TYPE_PURCHASE;
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
     * @since version 1.3.0
     */
    public function getPreAuthExpDate(): string
    {
        return $this->params[self::PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME] ?? '2h';
    }

    /**
     *
     * @return string
     *
     * @since version 1.3.0
     */
    public function getPreAuthPendingStatus(): string
    {
        return $this->params[self::PAYMENT_PREAUTH_PENDING_STATUS_CONFIG_NAME] ?? '';
    }

    /**
     *
     * @return string
     *
     * @since version 1.3.0
     */
    public function getCompletionTriggerStatus(): string
    {
        return $this->params[self::PAYMENT_COMPLETION_TRIGGER_STATUS_CONFIG_NAME] ?? '';
    }

    /**
     * @param string $code
     *
     * @return int
     *
     * @since version 1.4.0
     */
    public function getCurrencyCode(string $code): int
    {
        if (!array_key_exists($code, self::CURRENCY_CODE_MAP)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Currency "%s" is not supported by AlliancePay. Supported currencies: %s.',
                    $code,
                    implode(', ', array_keys(self::CURRENCY_CODE_MAP))
                )
            );
        }

        return self::CURRENCY_CODE_MAP[$code];
    }

    /**
     * @param int $numericCode
     *
     * @return string|null
     *
     * @since version 1.4.0
     */
    public function getCurrencyNameByNumericCode(int $numericCode): ?string
    {
        $flipped = array_flip(self::CURRENCY_CODE_MAP);

        return $flipped[$numericCode] ?? null;
    }

    /**
     * @param int $vendorId  VirtueMart vendor ID (typically 1)
     *
     * @return bool
     *
     * @since version 1.4.0
     */
    public function hasNonUahAcceptedCurrency(int $vendorId = 1): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('c.currency_numeric_code'))
                ->from($this->db->quoteName('#__virtuemart_vendors', 'v'))
                ->join(
                    'INNER',
                    $this->db->quoteName('#__virtuemart_currencies', 'c')
                    . ' ON FIND_IN_SET('
                    . $this->db->quoteName('c.virtuemart_currency_id')
                    . ', ' . $this->db->quoteName('v.vendor_accepted_currencies') . ')'
                )
                ->where($this->db->quoteName('v.virtuemart_vendor_id') . ' = ' . (int) $vendorId)
                ->where($this->db->quoteName('c.currency_numeric_code') . ' != 980');

            $this->db->setQuery($query);
            $rows = $this->db->loadColumn();

            return !empty($rows);
        } catch (\Throwable $e) {
            Log::add(
                'hasNonUahAcceptedCurrency: DB error: ' . $e->getMessage(),
                Log::WARNING,
                'alliance_payment'
            );

            return false;
        }
    }

    /**
     * @return bool
     *
     * @since version 1.5.0
     */
    public function isSubscriptionsEnabled(): bool
    {
        return (string) ($this->params[self::SUBSCRIPTIONS_ENABLED_CONFIG_NAME] ?? '0') === '1';
    }

    /**
     * Minimum number of days between a mass-reprice action and the moment new rules take effect.
     *
     * @return int
     *
     * @since version 1.5.0
     */
    public function getSubscriptionRepriceMinDays(): int
    {
        $days = (int) ($this->params[self::SUBSCRIPTION_REPRICE_MIN_DAYS_CONFIG_NAME]
            ?? self::DEFAULT_SUBSCRIPTION_REPRICE_MIN_DAYS);

        return $days > 0 ? $days : self::DEFAULT_SUBSCRIPTION_REPRICE_MIN_DAYS;
    }

    /**
     * Content-Type of signed requests to the scheduled_payments/* endpoint group.
     *
     * @return string
     *
     * @since version 1.5.0
     */
    public function getSubscriptionContentType(): string
    {
        $type = (string) ($this->params[self::SUBSCRIPTION_CONTENT_TYPE_CONFIG_NAME] ?? '');

        // VirtueMart stores each plugin param value JSON-encoded (vmJsApi::safe_json_encode),
        // which escapes "/" to "\/"; our own PipeParser::decode() does not undo that. Same
        // workaround as getApiUrl()/getJwsPrivateKey()/getAuthorizationKey() above.
        if ($type !== '') {
            $type = str_replace('\\', '', $type);
        }

        return $type !== '' ? $type : self::DEFAULT_SUBSCRIPTION_CONTENT_TYPE;
    }

    /**
     * @return bool  Whether the x-api_version header is sent to the scheduled_payments/* group.
     *
     * @since version 1.5.0
     */
    public function isSubscriptionApiVersionHeaderSent(): bool
    {
        return (string) ($this->params[self::SUBSCRIPTION_SEND_API_VERSION_CONFIG_NAME] ?? '0') === '1';
    }
}
