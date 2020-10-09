<?php

namespace TingYing\Container;


// Bind 主要提供容器绑定所有动作
trait Resolved
{
    public function sayWorld()
    {
        echo 'World!';
    }
}
