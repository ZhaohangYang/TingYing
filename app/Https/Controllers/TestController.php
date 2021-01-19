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

        // retrieve GET and POST variables respectively
        // GET
        $request->query->get('foo');
        // POST
        $request->request->get('bar', 'default value if bar does not exist');
        // retrieve SERVER variables
        $request->server->get('HTTP_HOST');

        // retrieves an instance of UploadedFile identified by foo
        $request->files->get('foo');

        // retrieve a COOKIE value
        $request->cookies->get('PHPSESSID');

        // retrieve an HTTP request header, with normalized, lowercase keys
        $request->headers->get('host');
        $request->headers->get('content-type');

        $request->getMethod();    // GET, POST, PUT, DELETE, HEAD
        $request->getLanguages(); // an array of languages the client accepts

        $name = $request->get('name', 'world');

        // 模拟一个请求
        $request = Request::create('/index.php?name=Fabien');

        // 使用Response该类，您可以调整响应：
        $response = new Response();

        $response->setContent('Hello world!');
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/html');
        // configure the HTTP cache headers
        $response->setMaxAge(10);


        // 即使只是简单地获得客户端IP地址，也可能是不安全的：



        // 伙伴初始化方法
        Huoban::init(HuobanConfig::getHuobanConfig());

        return new Response('Hello ' . $name);
    }
}
