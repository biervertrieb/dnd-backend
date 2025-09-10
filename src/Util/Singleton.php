<?php

namespace App\Util;

class Singleton
{
    private static array $instances = [];

    public static function getInstance(string $class)
    {
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    protected function __construct()
    {
        // Prevent direct instantiation
    }

    protected function __clone()
    {
        // Prevent cloning
    }

    protected function __wakeup()
    {
        // Prevent unserialization
    }
}
