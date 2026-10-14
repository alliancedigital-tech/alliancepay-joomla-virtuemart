<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

\defined('_JEXEC') or die;

use Alliance\Plugin\Task\Alliancepay\Extension\Alliancepay;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Alliancepay((array) PluginHelper::getPlugin('task', 'alliancepay'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
