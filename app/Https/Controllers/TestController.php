<?php

namespace App\Https\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Huoban\Huoban;
use App\Config\HuobanConfig;

class TestController
{

    public function index(Request $request)
    {
        $name = $request->get('name', 'world');
        Huoban::init(HuobanConfig::getHuobanConfig());

        return new Response('Hello ' . $name);
    }
}
