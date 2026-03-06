<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption;

use Exception;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\JWEFactory;
use Joomla\CMS\Log\Log;
use SimpleJWT\JWE;
use SimpleJWT\Keys\KeyFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption
 *
 * @since version 1.0.0
 */
class JweEncryptionService
{
    private const ALGORITHM = 'ECDH-ES+A256KW';

    private const ENCRYPTION = 'A256GCM';

    private $headers = [
        'alg' => self::ALGORITHM,
        'enc' => self::ENCRYPTION
    ];

    public function __construct(
        private readonly KeyFactory $keyFactory,
        private readonly KeySetFactory $keySetFactory,
        private readonly JWEFactory $jweFactory
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param array $data
     * @param array $publicServerKey
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function encrypt(array $data, array $publicServerKey): string
    {
        $dataJson = json_encode($data);
        $key = $this->keyFactory::create(
            $publicServerKey,
            alg: self::ALGORITHM
        );
        $keySet = $this->keySetFactory->create();
        $keySet->add($key);
        $jwe = $this->jweFactory->create(
            $this->headers,
            $dataJson
        );

        try {
            return $jwe->encrypt($keySet);
        } catch (Exception $e) {
            Log::add('Encryption failed: ' . $e->getMessage(), Log::ERROR);
        }

        return '';
    }

    /**
     * @param string $authentificationKey
     * @param string $jweToken
     *
     * @return array
     *
     * @since version 1.0.0
     */
    public function decrypt(string $authentificationKey, string $jweToken): array
    {
        $decryptData = [];
        $key = $this->keyFactory::create(
            $authentificationKey, 'json', alg:self::ALGORITHM
        );
        $keySet = $this->keySetFactory->create();
        $keySet->add($key);

        try {
            $jweObj = JWE::decrypt(
                $jweToken,
                $keySet,
                self::ALGORITHM
            );
        } catch (Exception $e) {
            Log::add('Decryption failed: ' . $e->getMessage(), Log::ERROR);
        }

        $decryptPlainText = $jweObj->getPlaintext();

        if ($decryptPlainText) {
            $decryptData = json_decode($decryptPlainText, true);
        }

        return $decryptData;
    }
}
