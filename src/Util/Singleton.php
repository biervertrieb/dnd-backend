<?php

namespace App\Util;

class Singleton
{
    private static array $instances = [];

    public static function getInstance()
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
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

    public function __wakeup()
    {
        // Prevent unserialization
    }
}
