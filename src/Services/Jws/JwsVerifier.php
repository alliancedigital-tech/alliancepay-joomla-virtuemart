<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AlliancePublicKeyCache;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory\HttpClientFactory;
use GuzzleHttp\Psr7\HttpFactory;
use RuntimeException;
use SimpleJWT\InvalidTokenException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\ECKey;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws
 *
 * @since version 1.5.0
 */
class JwsVerifier
{
    private const METHOD_POST = 'POST';

    private const ENDPOINT_PUBLIC_KEY = '/ecom/keys/public_key/get_v1';

    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly KeySetFactory $keySetFactory,
        private readonly HttpClientFactory $httpClientFactory,
        private readonly HttpFactory $httpFactory,
        private readonly Config $config,
        private readonly AlliancePublicKeyCache $publicKeyCache
    ) {
    }

    /**
     * @param string $jws  Compact JWS (header.payload.signature)
     *
     * @return array  Verified payload claims
     *
     * @since version 1.5.0
     */
    public function verify(string $jws): array
    {
        try {
            $deserialised = JWT::deserialise($jws);
        } catch (Throwable $e) {
            throw new RuntimeException('JWS callback parse error: ' . $e->getMessage(), 0, $e);
        }

        $headers = $deserialised['signatures'][0]['headers'] ?? [];
        $kid = $headers['kid'] ?? null;
        $alg = $headers['alg'] ?? null;

        if (empty($kid) || empty($alg)) {
            throw new RuntimeException('JWS callback is missing kid or alg in header.');
        }

        $jwk = $this->fetchPublicKey($kid);

        try {
            $key = new ECKey($jwk, 'php');
            $key->setKeyId($kid);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Invalid public key response from bank for kid=' . $kid . ': ' . $e->getMessage(),
                0,
                $e
            );
        }

        $keySet = $this->keySetFactory->create();
        $keySet->add($key);

        try {
            $jwt = JWT::decode($jws, $keySet, $alg);
        } catch (InvalidTokenException $e) {
            throw new RuntimeException('JWS verification failed: ' . $e->getMessage(), 0, $e);
        }

        return $jwt->getClaims();
    }

    /**
     * @param string $kid
     *
     * @return array  JWK (kty, crv, x, y, kid, alg)
     *
     * @since version 1.5.0
     */
    private function fetchPublicKey(string $kid): array
    {
        $this->publicKeyCache->reset();
        $this->publicKeyCache->load($kid, 'kid');

        if (
            !empty($this->publicKeyCache->kid)
            && strtotime((string) $this->publicKeyCache->expires_at) > time()
        ) {
            $cached = json_decode((string) $this->publicKeyCache->jwk, true);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $baseUrl = $this->config->getApiUrl();

        try {
            $client = $this->httpClientFactory->create($baseUrl);
            $response = $client->send(
                $this->httpFactory->createRequest(
                    self::METHOD_POST,
                    $baseUrl . self::ENDPOINT_PUBLIC_KEY
                ),
                [
                    'json' => ['kid' => $kid],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Cannot fetch bank public key (kid=' . $kid . '): ' . $e->getMessage(),
                0,
                $e
            );
        }

        $jwk = json_decode($response->getBody()->getContents(), true);

        if (
            !is_array($jwk)
            || empty($jwk['kty'])
            || empty($jwk['crv'])
            || empty($jwk['x'])
            || empty($jwk['y'])
        ) {
            throw new RuntimeException('Invalid public key response from bank for kid=' . $kid);
        }

        $this->publicKeyCache->reset();
        $this->publicKeyCache->load($kid, 'kid');
        $this->publicKeyCache->bind([
            'kid' => $kid,
            'jwk' => json_encode($jwk),
            'expires_at' => date('Y-m-d H:i:s', time() + self::CACHE_TTL_SECONDS),
        ]);
        $this->publicKeyCache->check();
        $this->publicKeyCache->store();

        return $jwk;
    }
}
