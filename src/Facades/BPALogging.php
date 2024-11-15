<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPALogging extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.logging';
    }
    
    public static function emergency($message, array $context = []): void
    {
        static::log('emergency', $message, $context);
    }
    
    public static function alert($message, array $context = []): void
    {
        static::log('alert', $message, $context);
    }
    
    public static function critical($message, array $context = []): void
    {
        static::log('critical', $message, $context);
    }
    
    public static function error($message, array $context = []): void
    {
        static::log('error', $message, $context);
    }
    
    public static function warning($message, array $context = []): void
    {
        static::log('warning', $message, $context);
    }
    
    public static function notice($message, array $context = []): void
    {
        static::log('notice', $message, $context);
    }
    
    public static function info($message, array $context = []): void
    {
        static::log('info', $message, $context);
    }
    
    public static function debug($message, array $context = []): void
    {
        static::log('debug', $message, $context);
    }
}
