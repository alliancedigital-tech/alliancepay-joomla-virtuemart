<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory;

use SimpleJWT\Keys\KeySet;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Encryption\Factory
 *
 * @since version 1.0.0
 */
class KeySetFactory
{
    /**
     *
     * @return KeySet
     *
     * @since version 1.0.0
     */
    public function create(): KeySet
    {
        return new KeySet();
    }
}
