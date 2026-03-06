<?php
/**
 * @copyright Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 *
 * @license
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Authorization;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\JweEncryptionService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\HttpClient;
use Exception;
use Joomla\CMS\Log\Log;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Services\Authorization
 *
 * @since version 1.0.0
 */
class AuthorizationService
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly JweEncryptionService $jweEncryptionService,
        private readonly Config $config
    ) {
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
     * @since version 1.0.0
     */
    public function authorize(string $serviceCode): array
    {
        if (empty($serviceCode)) {
            return [];
        }

        try {
            $authorizationResult = $this->client->authorize($serviceCode);
        } catch (Exception $exception) {
            Log::add($exception->getMessage(), Log::ERROR);

            return [];
        }

        if (!empty($authorizationResult['jwe'])) {
            $authorizationKey = $this->config->getAuthorizationKey();
            $authData = $this->jweEncryptionService->decrypt(
                $authorizationKey,
                $authorizationResult['jwe']
            );
            $this->config->saveAuthentificationData($authData);

            return $authData;
        } elseif (isset($authResult['msgType']) && $authResult['msgType'] === 'ERROR') {
            return [];
        }

        return [];
    }
}
