<?php

use Hummingbird\Framework;
use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

$routes = require_once BASE_PATH . '/routes/api.php';

$container = new DependencyInjection\ContainerBuilder();

$container->register('context', Routing\RequestContext::class);
$container->register('matcher', Routing\Matcher\UrlMatcher::class)
    ->setArguments([$routes, new Reference('context')]);

$container->register('request_stack', HttpFoundation\RequestStack::class);
$container->register('controller_resolver', HttpKernel\Controller\ControllerResolver::class);
$container->register('argument_resolver', HttpKernel\Controller\ArgumentResolver::class);

$container->register('listener.router', HttpKernel\EventListener\RouterListener::class)
    ->setArguments([new Reference('matcher'), new Reference('request_stack')]);

$container->register('listener.response', HttpKernel\EventListener\ResponseListener::class)
    ->setArguments(['UTF-8']);

$container->register('listener.exception', HttpKernel\EventListener\ErrorListener::class)
    ->setArguments(['App\Https\Controllers\ErrorController::exception']);

$container->register('dispatcher', EventDispatcher\EventDispatcher::class)
    ->addMethodCall('addSubscriber', [new Reference('listener.router')])
    ->addMethodCall('addSubscriber', [new Reference('listener.response')])
    ->addMethodCall('addSubscriber', [new Reference('listener.exception')]);

$container->register('hummingbird', Framework::class)
    ->setArguments([
        new Reference('dispatcher'),
        new Reference('controller_resolver'),
        new Reference('request_stack'),
        new Reference('argument_resolver'),
    ]);

return $container;
