<?php

namespace TingYing\Container;


// Bind 主要提供容器绑定所有动作
trait Bind
{
    public function sayHello()
    {
        $this->sayWorld();
    }
}
