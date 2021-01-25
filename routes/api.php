
<?php

use Symfony\Component\Routing;

$routes = new Routing\RouteCollection();

$routes->add('is_leap_year', new Routing\Route('/is_leap_year/{year}', [
    'year' => null,
    '_controller' => 'App\Https\Controllers\TestController::index',
]));

return $routes;
