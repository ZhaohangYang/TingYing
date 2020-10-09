<?php
require __DIR__ . '/../vendor/autoload.php';

use TingYing\Container\Container;

$container = Container::getInstance();

$data = $container->sayHello();
var_dump($data);
