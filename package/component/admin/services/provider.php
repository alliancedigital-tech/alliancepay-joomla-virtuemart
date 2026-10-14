<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Extension\AlliancepayComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Alliance\\Component\\Alliancepay'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Alliance\\Component\\Alliancepay'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new AlliancepayComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
