<?php

namespace TingYing\Container;

use Closure;
use ReflectionNamedType;

/**
 * @internal
 */
class Util
{
    /**
     * If the given value is not an array and not null, wrap it in one.
     *
     * From Arr::wrap() in Illuminate\Support.
     *
     * @param  mixed  $value
     * @return array
     */
    public static function arrayWrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Return the default value of the given value.
     *
     * From global value() helper in Illuminate\Support.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function unwrapIfClosure($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    // 如果可能，获取给定参数类型的类名。
    public static function getParameterClassName($parameter)
    {
        $type = $parameter->getType();
        // ReflectionType 类用于获取函数、类方法的参数或者返回值的类型
        // ReflectionNamedType extends ReflectionType ，并且多了一个public getName ( void ) : string

        // 如果这个参数是实体，或者内置变量
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return;
        }

        $name = $type->getName();
        // 获取反射方法的声明类，如果不为空
        if (!is_null($class = $parameter->getDeclaringClass())) {
            if ($name === 'self') {
                return $class->getName();
            }

            if ($name === 'parent' && $parent = $class->getParentClass()) {
                return $parent->getName();
            }
        }

        return $name;
    }
}
