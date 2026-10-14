<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\RequestIdentification\GenerateRequestIdentification;
use RuntimeException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeyFactory;
use Throwable;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws
 *
 * @since version 1.5.0
 */
class JwsSigner
{
    private const ALGORITHM = 'RS256';

    public function __construct(
        private readonly KeySetFactory $keySetFactory
    ) {
    }

    /**
     * @param string $privateKeyPem
     * @param string $kid
     * @param string $targetUrl
     * @param array $payload
     *
     * @return string
     *
     * @since version 1.5.0
     */
    public function sign(string $privateKeyPem, string $kid, string $targetUrl, array $payload): string
    {
        if (empty($privateKeyPem)) {
            throw new RuntimeException('JWS Private Key (PEM) is not configured.');
        }

        if (empty($kid)) {
            throw new RuntimeException('JWS Key ID (kid) is not configured.');
        }

        try {
            $key = KeyFactory::create($privateKeyPem, 'pem');
            $key->setKeyId($kid);
        } catch (Throwable $e) {
            throw new RuntimeException('JWS Private Key is invalid: ' . $e->getMessage(), 0, $e);
        }

        $keySet = $this->keySetFactory->create();
        $keySet->add($key);

        $header = [
            'alg' => self::ALGORITHM,
            'ts' => (string) (int) (microtime(true) * 1000),
            'targetUrl' => $targetUrl,
            'kid' => $kid,
            'jti' => GenerateRequestIdentification::generateRequestId(),
        ];

        try {
            $jwt = new JWT($header, $payload);

            return $jwt->encode($keySet, null, false);
        } catch (Throwable $e) {
            throw new RuntimeException('JWS signing failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
