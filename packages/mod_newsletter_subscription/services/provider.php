<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module as ModuleServiceProvider;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\Zdebska\\Module\\NewsletterSubscription'));
        $container->registerServiceProvider(new HelperFactory('\\Zdebska\\Module\\NewsletterSubscription\\Site\\Helper'));
        $container->registerServiceProvider(new ModuleServiceProvider());
    }
};
