<?php

namespace App\Https\Controllers;

use App\Config\HuobanConfig;
use Huoban\Huoban;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
