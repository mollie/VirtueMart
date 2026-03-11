<?php

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Mollie\Payment\Bootstrap;

return new class implements ServiceProviderInterface
{
    private static bool $bootstrapInitialized = false;

    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        if (!self::$bootstrapInitialized
            && file_exists(JPATH_PLUGINS . '/vmpayment/mollie/include_mollie.php')
        ) {
            require_once JPATH_PLUGINS . '/vmpayment/mollie/include_mollie.php';
            Bootstrap::init();
            self::$bootstrapInitialized = true;
        }

        $container->registerServiceProvider(new MVCFactory('\\Mollie\\Component\\Mollie'));

        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Mollie\\Component\\Mollie'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new MVCComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
