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
        var_dump($this);
        exit;
        // 伙伴初始化方法
        Huoban::init(HuobanConfig::getHuobanConfig());
        return new Response('Hello');
    }
}
