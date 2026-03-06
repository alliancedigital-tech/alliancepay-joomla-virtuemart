<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory;

use SimpleJWT\JWE;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory
 *
 * @since version 1.0.0
 */
class JWEFactory
{
    /**
     * @param array $headers
     * @param string $payload
     *
     * @return JWE
     *
     * @since version 1.0.0
     */
    public function create(array $headers, string $payload): JWE
    {
        return new JWE($headers, $payload);
    }
}
