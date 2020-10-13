<?php

namespace Core;

use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

$app = new ContainerBuilder();
$app->register('context', 'Symfony\Component\Routing\RequestContext');
$app->register('matcher', 'Symfony\Component\Routing\Matcher\UrlMatcher')
    ->setArguments(array(getCollection(), new Reference('context')));

$app->register('requestStack', 'Symfony\Component\HttpFoundation\RequestStack');
$app->register('controllerResolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');
$app->register('argumentResolver', 'Symfony\Component\HttpKernel\Controller\ArgumentResolver');

$app->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
    ->setArguments(array(new Reference('matcher'), new Reference('requestStack')));

$app->register('dispatcher', 'Symfony\Component\EventDispatcher\EventDispatcher')
    ->addMethodCall('addSubscriber', array(new Reference('listener.router')));

$app->register('hummingbird', 'Core\Hummingbird')
    ->setArguments(array(
        new Reference('dispatcher'),
        new Reference('controllerResolver'),
        new Reference('requestStack'),
        new Reference('argumentResolver'),
    ));


return $app;

function getCollection()
{
    return (new YamlFileLoader(
        new FileLocator(array(getcwd()))
    ))->load('../routes/web.yaml');
}
