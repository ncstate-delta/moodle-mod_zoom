<?php
/**
 * Abstract class for Basic Enumeration
 */
abstract class BaseEnum
{
    public static function toArray()
    {
        $class = \get_called_class();
        $reflection  = new \ReflectionClass($class);
        return $reflection->getConstants();
    }

    /**
     * Returns the value of the key passed
     *
     * @param string $key
     * @return mixed
     */
    public static function valueOf($key)
    {
        $array = static::toArray();
        return $array[$key];
    }

    /**
     * Return key for value
     *
     * @param $value
     * @return mixed
     */
    public static function search($value)
    {
        return \array_search($value, static::toArray());
    }

    /**
     * Return the values to display
     *
     * @return array
     */
    public static function getValuesToDisplay()
    {
        return array_map(function ($value) {
            return ucfirst(str_replace('_', ' ', strtolower($value)));
        }, array_flip(static::toArray()));
    }
}

/**
 * Class RecurringFrequency
 */
class RecurringFrequency extends BaseEnum
{
    const DAILY = 1;

    const WEEKLY = 2;

    const MONTHLY = 3;
}

/**
 * Class DaysOfWeek
 */
class DaysOfWeek extends BaseEnum
{
    const SUNDAY = 1;

    const MONDAY = 2;

    const TUESDAY = 3;

    const WEDNESDAY = 4;

    const THURSDAY =  5;

    const FRIDAY =  6;

    const SATURDAY = 7;
}

/**
 * Class MonthlyWeek
 */
class MonthlyWeek extends BaseEnum
{
    const LAST_WEEK = -1;

    const FIRST_WEEK = 1;

    const SECOND_WEEK = 2;

    const THIRD_WEEK = 3;

    const FOURTH_WEEK = 4;
}

/**
 * Class EndType
 */
class EndType extends BaseEnum
{
    const END_BY_DATE = 1;

    const END_AFTER_X_OCCURRENCE = 2;
}