<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Helper;

defined('_JEXEC') or die;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Bootstrap\ServiceRegistry;
use Joomla\DI\Container;
use RuntimeException;

/**
 * Class Services.
 *
 * @since 1.5.0
 */
final class Services
{
    private static ?Container $container = null;

    /**
     * @param string $class
     * @return object
     */
    public static function get(string $class): object
    {
        if (self::$container === null) {
            if (!class_exists(ServiceRegistry::class)) {
                $autoload = JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';

                if (is_file($autoload)) {
                    require_once $autoload;
                }
            }

            if (!class_exists(ServiceRegistry::class)) {
                throw new RuntimeException('The AlliancePay payment plugin (vmpayment/alliancepay) is not installed.');
            }

            self::$container = ServiceRegistry::createContainer();
        }

        return self::$container->get($class);
    }
}
