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
use SimpleJWT\Keys\ECKey;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeyInterface;
use SimpleJWT\Keys\RSAKey;
use Throwable;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws
 *
 * @since version 1.5.0
 */
class JwsSigner
{
    public const ALGORITHM_EC = 'ES256';
    public const ALGORITHM_RSA = 'RS256';

    private const EC_CURVE = 'P-256';

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

        if ($key->isPublic()) {
            throw new RuntimeException('JWS Private Key is invalid: a public key was provided.');
        }

        $algorithm = $this->resolveAlgorithm($key);

        $keySet = $this->keySetFactory->create();
        $keySet->add($key);

        $header = [
            'alg' => $algorithm,
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

    /**
     * Picks the JWS algorithm from the loaded private key: EC P-256 -> ES256, RSA -> RS256
     * (kept so installations configured with an RSA key keep working).
     *
     * @param KeyInterface $key
     *
     * @return string
     *
     * @since version 1.6.0
     */
    private function resolveAlgorithm(KeyInterface $key): string
    {
        if ($key instanceof ECKey) {
            if ($key->getCurve() !== self::EC_CURVE) {
                throw new RuntimeException(
                    sprintf(
                        'JWS Private Key uses the unsupported curve %s; an EC key on %s is required (ES256).',
                        $key->getCurve(),
                        self::EC_CURVE
                    )
                );
            }

            return self::ALGORITHM_EC;
        }

        if ($key instanceof RSAKey) {
            return self::ALGORITHM_RSA;
        }

        throw new RuntimeException(
            'JWS Private Key type is not supported; use an EC P-256 key (ES256) or an RSA key (RS256).'
        );
    }
}
