<?php

namespace TingYing\Container;

use Exception;
use Closure;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

use TingYing\Container\Util;
use TingYing\Container\RewindableGenerator;
use TingYing\Container\ContextualBindingBuilder;

use TingYing\Standard\Container\Container as StandardContainer;
use TingYing\Standard\Container\NotFoundException;
use TingYing\Standard\Container\BindingResolutionException;
// Container 主要提供绑定、解析两个动作
class Container implements StandardContainer
{

    // 单例模式，特指当前容器
    protected static $instance;

    // 已经解析完成的标识符集合
    protected $resolved = [];

    // 容器中各类标识符的绑定解析方法的集合
    protected $bindings = [];

    // 容器的方法绑定
    protected $methodBindings = [];

    // 容器的共享实例（根据标识符解析）
    protected $instances = [];

    // 别名对应标识符的集合
    protected $aliases = [];

    // 容器中各个标识符对应的别名集合
    protected $abstractAliases = [];

    //  标识符的扩展闭包
    protected $extenders = [];

    // 所有注册的标签
    protected $tags = [];

    // 目前需要解析的类的栈
    protected $buildStack = [];

    // 目前需要解析的参数的栈
    protected $with = [];

    // 上下文绑定映射(当已经限定了当前的形参实例，或者实参值，将会在这个参数中找到)
    public $contextual = [];



    // 标识符绑定要回调集合
    protected $reboundCallbacks = [];

    // 所有全局解析回调集合
    protected $globalResolvingCallbacks = [];

    // 所有全局解析回调完成之后的回调
    protected $globalAfterResolvingCallbacks = [];

    // 标识符解析之后的回调集合
    protected $resolvingCallbacks = [];

    // 标识符解析回调完成之后的回调
    protected $afterResolvingCallbacks = [];



    /********************* 绑定 *********************/

    // 返回一个ContextualBindingBuilder类，主要用于一个解析动作，传入实参
    public function when($concrete)
    {
        $aliases = [];

        foreach (Util::arrayWrap($concrete) as $c) {
            $aliases[] = $this->getAlias($c);
        }

        return new ContextualBindingBuilder($this, $aliases);
    }

    // 绑定动作
    public function bind($id, $concrete = null, $shared = false)
    {
        unset($this->instances[$id], $this->aliases[$id]);
        // 如果没有提供绑定的解析方式，就默认传入的参数即要解析的方式名称
        if (is_null($concrete)) {
            $concrete = $id;
        }
        // 如果实现方式不是匿名函数类
        if (!$concrete instanceof Closure) {
            // 如果实现方式不是字符串，抛出异常
            if (!is_string($concrete)) {
                throw new \TypeError(self::class . '::bind(): Argument #2 ($concrete) must be of type Closure|string|null');
            }
            $concrete = $this->getClosure($id, $concrete);
        }
        // 把绑定存入绑定关系数组
        $this->bindings[$id] = compact('concrete', 'shared');
        // 如果标识符已经被解析过，覆盖他，重新绑定
        if ($this->resolved($id)) {
            $this->rebound($id);
        }
    }

    // 如果给定标识符没有被绑定执行一次绑定动作
    public function bindIf($id, $concrete = null, $shared = false)
    {
        if (!$this->bound($id)) {
            $this->bind($id, $concrete, $shared);
        }
    }

    // 绑定标识符，(单例，可分享的)
    public function singleton($id, $concrete = null)
    {
        $this->bind($id, $concrete, true);
    }

    // 如果给定标识符没有被绑定,绑定标识符，(单例，可分享的)
    public function singletonIf($abstract, $concrete = null)
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }


    // 添加一个上下文绑定
    public function addContextualBinding($concrete, $id, $implementation)
    {
        $this->contextual[$concrete][$this->getAlias($id)] = $implementation;
    }

    // 将现有实例注册为容器中的共享实例。
    public function instance($abstract, $instance)
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        unset($this->aliases[$abstract]);

        // We'll check to determine if this type has been bound before, and if it has
        // we will fire the rebound callbacks registered with the container and it
        // can be updated with consuming classes that have gotten resolved here.
        $this->instances[$abstract] = $instance;

        if ($isBound) {
            $this->rebound($abstract);
        }

        return $instance;
    }


    // 返回一个标识符的解析方法
    protected function getClosure($id, $concrete)
    {
        return function ($container, $parameters = []) use ($id, $concrete) {
            if ($id == $concrete) {
                return $container->build($id);
            }

            return $container->resolve(
                $concrete,
                $parameters,
                $raiseEvents = false
            );
        };
    }

    // 获取标识符对应的别名，如果不存在返回标识符本身
    public function getAlias($id)
    {
        return isset($this->aliases[$id]) ? $this->getAlias($this->aliases[$id]) : $id;
    }

    // 获取标识符被解析之后的扩展程序
    protected function getExtenders($id)
    {
        return $this->extenders[$this->getAlias($id)] ?? [];
    }

    // 取消标识符被解析之后的扩展程序
    public function forgetExtenders($id)
    {
        unset($this->extenders[$this->getAlias($id)]);
    }

    // 重新绑定
    protected function rebound($id)
    {
        $instance = $this->make($id);

        foreach ($this->getReboundCallbacks($id) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    // 执行标识符绑定之后的回调函数
    protected function getReboundCallbacks($id)
    {
        return $this->reboundCallbacks[$id] ?? [];
    }

    /********************* 扩展程序 *********************/

    // 添加标识符被解析之后的扩展程序
    public function extend($id, Closure $closure)
    {
        $id = $this->getAlias($id);

        if (isset($this->instances[$id])) {
            // 如果已经解析过，就直接执行扩展程序的闭包，所得到结果直接覆盖原来的解析结果，并重新绑定
            $this->instances[$id] = $closure($this->instances[$id], $this);

            $this->rebound($id);
        } else {
            // 如果没有解析过，添加扩展程序，就重新解析，重新绑定
            $this->extenders[$id][] = $closure;

            if ($this->resolved($id)) {
                $this->rebound($id);
            }
        }
    }

    /********************* 状态验证 *********************/

    // 被绑定的标识符解析结果是否共享
    public function isShared($id)
    {
        return isset($this->instances[$id]) ||
            (isset($this->bindings[$id]['shared']) &&
                $this->bindings[$id]['shared'] === true);
    }

    // 给定的标识符是否在容器中,已经被绑定、解析、映射;
    public function bound($id)
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || isset($this->aliases[$id]);
    }

    // 标识符是否已经被解析
    public function resolved($id)
    {
        if ($this->isAlias($id)) {
            $id = $this->getAlias($id);
        }

        return isset($this->resolved[$id]) || isset($this->instances[$id]);
    }

    // 给定标识符是否有别名
    public function isAlias($id)
    {
        return isset($this->aliases[$id]);
    }

    /********************* 解析 *********************/

    // 创建一个解析之后的实例
    public function make($id, array $parameters = [])
    {
        return $this->resolve($id, $parameters);
    }

    // 从容器中解析给定的标识符
    protected function resolve($id, $parameters = [], $raiseEvents = true)
    {
        $id = $this->aliases[$id] ?? $id;

        $concrete = $this->getContextualConcrete($id);

        // 如果传参不变，解析结果也不会变
        $needsContextualBuild = !empty($parameters) || !is_null($concrete);

        // 如果已经解析，并且解析时不需要额外的参数，就返回上一次解析的结果
        if (isset($this->instances[$id]) && !$needsContextualBuild) {
            return $this->instances[$id];
        }

        $this->with[] = $parameters;

        if (is_null($concrete)) {
            // 获取标识符解析方法
            $concrete = $this->getConcrete($id);
        }

        // 递归解析;能实例化解析，实例化解析返回，不能实例化解析，递归去解析
        if ($this->isBuildable($concrete, $id)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }

        // 执行解析后的扩展程序，可以理解为解析后的钩子操作
        foreach ($this->getExtenders($id) as $extender) {
            $object = $extender($object, $this);
        }

        // 如果标识符的参数不能被重写，并且本身是可以共享的，则进行缓存，不必每次都要解析
        if ($this->isShared($id) && !$needsContextualBuild) {
            $this->instances[$id] = $object;
        }

        if ($raiseEvents) {
            // 启动所有解析回调。
            $this->fireResolvingCallbacks($id, $object);
        }

        // 标识此标识符已经被解析
        $this->resolved[$id] = true;
        // 能执行到此处，证明标识符解析的方式为实例化类，实例化完成，出栈对应的参数集合
        array_pop($this->with);

        return $object;
    }

    // 获取标识符解析方法
    protected function getConcrete($id)
    {
        // 已经绑定返回绑定的方法
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id]['concrete'];
        }
        // 没有绑定，说明他的解析方法就是它本身，返回标识符本身
        return $id;
    }

    // 递归的获取所需要的所有的解析方法
    protected function getContextualConcrete($id)
    {
        if (!is_null($binding = $this->findInContextualBindings($id))) {
            return $binding;
        }

        if (empty($this->abstractAliases[$id])) {
            return;
        }

        foreach ($this->abstractAliases[$id] as $alias) {
            if (!is_null($binding = $this->findInContextualBindings($alias))) {
                return $binding;
            }
        }
    }

    // 当前标识符解析的时候是否有依赖的绑定，如果有返回给定的依赖，反之返回null(场景，当解析上一级的标识符的时候，如果当前标识符已经给定的解析方式，咋返回给定的解析方式)
    protected function findInContextualBindings($id)
    {
        return $this->contextual[end($this->buildStack)][$id] ?? null;
    }

    // 标识符是否可以被解析（是一个类名，或者是一个闭包函数就可以被解析）
    protected function isBuildable($concrete, $id)
    {
        return $concrete === $id || $concrete instanceof Closure;
    }

    // 通过一个解析方法(可能是类名)，创建一个解析的实例
    public function build($concrete)
    {
        // 如果是一个闭包函数，直接执行它，返回结果就ok
        if ($concrete instanceof Closure) {
            // 执行闭包函数，（容器本身，出栈的一个参数结合）
            return $concrete($this, $this->getLastParameterOverride());
        }

        // 如果是一个类名，通过反射解析它
        try {
            // 获取一个雷的反射
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [$concrete] does not exist.", 0, $e);
        }

        // 如果不能实例化
        if (!$reflector->isInstantiable()) {
            return $this->notInstantiable($concrete);
        }

        // 入栈要实例化的普通类、抽象类，接口；解析完成后出栈
        $this->buildStack[] = $concrete;

        // 获取构造函数
        $constructor = $reflector->getConstructor();

        //如果没有构造函数，则意味着没有依赖关系；我们可以直接解析对象的实例，而不需要；从这些容器中解析任何其他类型或依赖关系
        if (is_null($constructor)) {
            // 在解析之前出栈
            array_pop($this->buildStack);

            return new $concrete;
        }

        // 获取执行构造函数所需要的参数
        $dependencies = $constructor->getParameters();

        //一旦我们有了构造函数的所有参数，我们就可以创建；依赖关系实例，然后使用反射实例使；此类的新实例，将创建的依赖项注入。

        try {
            // 解析上面需要的参数，因为参数有可能是对象等类型
            $instances = $this->resolveDependencies($dependencies);
        } catch (BindingResolutionException $e) {
            array_pop($this->buildStack);
            throw $e;
        }

        // 在解析之前出栈
        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    //  逐一解析参数集合
    protected function resolveDependencies(array $dependencies)
    {
        // 参数解析的最终结果
        $results = [];

        foreach ($dependencies as $dependency) {

            // 如果参数重写了，记录重新之后的参数值
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);
                continue;
            }

            // 如果类为null，则表示依赖关系是字符串或其他类型；无法解析的基元类型，因为它不是类并且；因为我们无处可去，我们只会犯一个错误。
            // 如果返回值空，证明参数是实际变量、php内置变量、实际值，解析实参
            // 如果返回类名，解析类
            $result = is_null(Util::getParameterClassName($dependency))
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency);

            // 参数是否可变，可变的话以最后一次解析的结果为准
            if ($dependency->isVariadic()) {
                $results = array_merge($results, $result);
            } else {
                $results[] = $result;
            }
        }

        return $results;
    }

    // 给定参数是否被重新
    protected function hasParameterOverride($dependency)
    {
        return array_key_exists(
            $dependency->name,
            $this->getLastParameterOverride()
        );
    }

    // 返回给定参数重写之后的值
    protected function getParameterOverride($dependency)
    {
        return $this->getLastParameterOverride()[$dependency->name];
    }

    // 获取目前需要解析的，参数的，栈的，最后一个参数结合
    protected function getLastParameterOverride()
    {
        return count($this->with) ? end($this->with) : [];
    }

    // 解析实参
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        // 如果事先绑定了
        if (!is_null($concrete = $this->getContextualConcrete('$' . $parameter->getName()))) {
            // 实参是函数就执行函数，实参是变量直接返回变量
            return $concrete instanceof Closure ? $concrete($this) : $concrete;
        }

        // 如果有默认值，直接返回默认值
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        // 实参没有事先绑定也没有默认值，抛出异常
        $this->unresolvablePrimitive($parameter);
    }

    //  解析参数类
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            // 参数是否可变
            return $parameter->isVariadic()
                ? $this->resolveVariadicClass($parameter)
                : $this->make(Util::getParameterClassName($parameter));
        }

        //无法解析的前提下，如果有默认值，返回默认值；如果可变，返回空数组；既没有默认值，又不可变，抛出异常
        catch (BindingResolutionException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->isVariadic()) {
                return [];
            }

            throw $e;
        }
    }

    //  解析可变参数
    protected function resolveVariadicClass(ReflectionParameter $parameter)
    {
        // 获取参数类名
        $className = Util::getParameterClassName($parameter);

        $id = $this->getAlias($className);

        // 如果是一个参数，直接解析
        if (!is_array($concrete = $this->getContextualConcrete($id))) {
            return $this->make($className);
        }
        // 如果是多个参数，递归解析
        return array_map(function ($id) {
            return $this->resolve($id);
        }, $concrete);
    }

    // 抛出一个解析方法不能正常解析的异常。
    protected function notInstantiable($concrete)
    {
        if (!empty($this->buildStack)) {
            $previous = implode(', ', $this->buildStack);

            $message = "Target [$concrete] is not instantiable while building [$previous].";
        } else {
            $message = "Target [$concrete] is not instantiable.";
        }

        throw new BindingResolutionException($message);
    }

    // 启动所有解析回调。
    protected function fireResolvingCallbacks($id, $object)
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getCallbacksForType($id, $object, $this->resolvingCallbacks)
        );

        $this->fireAfterResolvingCallbacks($id, $object);
    }

    // 执行所有解析回调
    protected function fireCallbackArray($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($object, $this);
        }
    }

    //  获取标识符的所有回调
    protected function getCallbacksForType($id, $object, array $callbacksPerType)
    {
        $results = [];

        foreach ($callbacksPerType as $type => $callbacks) {
            if ($type === $id || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }

        return $results;
    }

    // 在当前标识符下，执行完所有的解析回调，执行全局的解析回调
    protected function fireAfterResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getCallbacksForType($abstract, $object, $this->afterResolvingCallbacks)
        );
    }

    // 对无法解析的原语引发异常
    protected function unresolvablePrimitive(ReflectionParameter $parameter)
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new BindingResolutionException($message);
    }

    /********************* 方法绑定 (区别于标识符绑定) *********************/

    // 确定容器是否具有给定的方法绑定
    public function hasMethodBinding($method)
    {
        return isset($this->methodBindings[$method]);
    }

    // 绑定回调以使用Container::call解析。
    public function bindMethod($method, $callback)
    {
        $this->methodBindings[$this->parseBindMethod($method)] = $callback;
    }

    // 获取要绑定的方法 "类@方法" 格式。
    protected function parseBindMethod($method)
    {
        if (is_array($method)) {
            return $method[0] . '@' . $method[1];
        }

        return $method;
    }

    // 执行给定方法的方法绑定
    public function callMethodBinding($method, $instance)
    {
        return call_user_func($this->methodBindings[$method], $instance, $this);
    }

    /********************* 标签 *********************/

    // 为给定的标识符集合，分配一组标记
    public function tag($ids, $tags)
    {
        $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array) $ids as $id) {
                $this->tags[$tag][] = $id;
            }
        }
    }

    // 解析给定标记的所有标识符。
    public function tagged($tag)
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return new RewindableGenerator(function () use ($tag) {
            foreach ($this->tags[$tag] as $id) {
                yield $this->make($id);
            }
        }, count($this->tags[$tag]));
    }

    /********************* 单例模式实现 *********************/

    // 获取全局定义的容器
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /********************* psr/container接口实现 *********************/

    // 按标识符在容器中查找对应的值，并返回它;
    public function get($id): void
    {
        try {
            return $this->resolve($id);
        } catch (Exception $e) {
            if ($this->has($id)) {
                throw $e;
            }
            throw new NotFoundException($id, $e->getCode(), $e);
        }
    }
    // 容器可以返回给定标识符的内容
    public function has($id): bool
    {
        return $this->bound($id);
    }
}
