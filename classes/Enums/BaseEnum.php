<?php

class BaseEnum
{
    public static function toArray()
    {
        $class = \get_called_class();
        $reflection  = new \ReflectionClass($class);
        return $reflection->getConstants();
    }

    public static function get()
    {
        return array_map(function ($value) {
            return str_replace('_', ' ', $value);
        }, array_flip(static::toArray()));
    }
}
