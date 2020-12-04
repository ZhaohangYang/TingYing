<?php
require_once __DIR__ . '/../vendor/autoload.php';

define('START_TIME', microtime(true));
define('BASE_PATH', str_replace('\\', '/', realpath(dirname(__FILE__) . '/../')));
$container = require_once __DIR__ . '/../framework/container/Container.php';

$response = $container->get('hummingbird')
    ->handle(
        Symfony\Component\HttpFoundation\Request::createFromGlobals()
    );

$response->send();
