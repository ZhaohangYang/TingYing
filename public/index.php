<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Container\Container;

$container = Container::getInstance();

$container->singleton('route', function () {
    return new FastRoute\RouteCollector(new FastRoute\RouteParser\Std(), new FastRoute\DataGenerator\GroupCountBased());
});
$container->singleton('dispatcher', function ($container, $route_data) {
    return new FastRoute\Dispatcher\GroupCountBased($route_data);
});



$route = $container->make('route');


$route->addRoute('GET', '/users', 'get_all_users_handler');
$route->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
$route->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');


// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);


$dispatcher = $container->make('dispatcher', $route->getData());
// $dispatcher = new FastRoute\Dispatcher\GroupCountBased($route->getData());
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        print_r($handler);
        print_r($vars);
        // ... call $handler with $vars
        break;
}

echo '<br>__________end';
