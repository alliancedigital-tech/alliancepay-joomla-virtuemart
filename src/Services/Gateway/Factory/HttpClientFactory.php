<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory;

use GuzzleHttp\Client;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Services\Gateway\Factory
 *
 * @since version 1.0.0
 */
class HttpClientFactory
{
    /**
     * @param string $baseUri
     * @param int $timeout
     *
     * @return Client
     *
     * @since version 1.0.0
     */
    public function create(string $baseUri, int $timeout = 10): Client
    {
        return new Client([
            'base_uri' => $baseUri,
            'timeout'  => $timeout,
        ]);
    }
}
