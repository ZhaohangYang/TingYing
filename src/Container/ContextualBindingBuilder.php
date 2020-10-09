<?php

namespace TingYing\Container;

class ContextualBindingBuilder
{
    // 定义的容器
    protected $container;

    // 解析的方法
    protected $concrete;

    // 抽象目标
    protected $needs;

    // ContextualBindingBuilder，构造函数
    public function __construct(Container $container, $concrete)
    {
        $this->concrete = $concrete;
        $this->container = $container;
    }

    // 标识符解析需要的形参
    public function needs($abstract)
    {
        $this->needs = $abstract;

        return $this;
    }

    // 定义标识符实现
    public function give($implementation)
    {
        foreach (Util::arrayWrap($this->concrete) as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }

    // 定义标记服务以作为上下文绑定的实现。
    public function giveTagged($tag)
    {
        $this->give(function ($container) use ($tag) {
            $taggedServices = $container->tagged($tag);

            return is_array($taggedServices) ? $taggedServices : iterator_to_array($taggedServices);
        });
    }
}
