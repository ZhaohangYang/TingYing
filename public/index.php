<?php
ini_set('display_errors', 1);
error_reporting(-1);

require __DIR__ . '/../vendor/autoload.php';





$app = require_once __DIR__ . '/../core/container.php';

$response = $app->get('hummingbird')
    ->handle(
        Symfony\Component\HttpFoundation\Request::createFromGlobals()
    );

$response->send();
