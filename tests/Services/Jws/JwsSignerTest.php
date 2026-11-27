<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Tests\Services\Jws;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory\KeySetFactory;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;

class JwsSignerTest extends TestCase
{
    private const KID = 'test-kid';
    private const TARGET = '/ecom/jws/cashbox/v2/fiscalization/create';

    private JwsSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new JwsSigner(new KeySetFactory());
    }

    public function testEcP256KeyProducesVerifiableEs256Signature(): void
    {
        $pem = $this->generatePem(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        $jws = $this->signer->sign($pem, self::KID, self::TARGET, ['a' => 1]);

        $header = $this->decodeHeader($jws);
        $this->assertSame('ES256', $header['alg']);
        $this->assertSame(self::KID, $header['kid']);
        $this->assertSame(self::TARGET, $header['targetUrl']);

        $claims = JWT::decode($jws, $this->publicKeySet($pem), 'ES256')->getClaims();
        $this->assertSame(['a' => 1], $claims);
    }

    public function testEcSignatureIsRawRsFormat(): void
    {
        $pem = $this->generatePem(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        $jws = $this->signer->sign($pem, self::KID, self::TARGET, ['a' => 1]);

        $signature = $this->base64UrlDecode(explode('.', $jws)[2]);
        $this->assertSame(64, strlen($signature), 'ES256 signature must be R||S (64 bytes), not ASN.1 DER.');
    }

    public function testRsaKeyStillSignsWithRs256(): void
    {
        $pem = $this->generatePem(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);

        $jws = $this->signer->sign($pem, self::KID, self::TARGET, ['a' => 1]);

        $this->assertSame('RS256', $this->decodeHeader($jws)['alg']);
        $claims = JWT::decode($jws, $this->publicKeySet($pem), 'RS256')->getClaims();
        $this->assertSame(['a' => 1], $claims);
    }

    public function testEcKeyOnAnotherCurveIsRejected(): void
    {
        $pem = $this->generatePem(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported curve P-384');

        $this->signer->sign($pem, self::KID, self::TARGET, ['a' => 1]);
    }

    public function testPublicKeyIsRejected(): void
    {
        $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $publicPem = openssl_pkey_get_details($private)['key'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWS Private Key is invalid');

        $this->signer->sign($publicPem, self::KID, self::TARGET, ['a' => 1]);
    }

    public function testGarbageKeyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWS Private Key is invalid');

        $this->signer->sign('not a pem', self::KID, self::TARGET, ['a' => 1]);
    }

    public function testEmptyKeyAndKidAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->signer->sign('', self::KID, self::TARGET, []);
    }

    private function generatePem(array $options): string
    {
        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key, 'openssl_pkey_new failed: ' . openssl_error_string());
        openssl_pkey_export($key, $pem);

        return $pem;
    }

    private function publicKeySet(string $privatePem): KeySet
    {
        $private = KeyFactory::create($privatePem, 'pem');
        $public = $private->getPublicKey();
        $public->setKeyId(self::KID);
        $set = new KeySet();
        $set->add($public);

        return $set;
    }

    private function decodeHeader(string $jws): array
    {
        return json_decode($this->base64UrlDecode(explode('.', $jws)[0]), true);
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
