<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController
{

    public function index(Request $request)
    {
        $name = $request->get('name', 'world');

        return new Response('Hello ' . $name);
    }
}
