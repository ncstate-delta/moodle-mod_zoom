<?php

abstract class BaseEnum
{
    public static function toArray()
    {
        $class = \get_called_class();
        $reflection  = new \ReflectionClass($class);
        return $reflection->getConstants();
    }

    public static function valueOf($key)
    {
        $array = static::toArray();
        return $array[$key];
    }

    public static function search($value)
    {
        return \array_search($value, static::toArray());
    }

    public static function getValuesToDisplay()
    {
        return array_map(function ($value) {
            return ucfirst(str_replace('_', ' ', strtolower($value)));
        }, array_flip(static::toArray()));
    }
}
