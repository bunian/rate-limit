<?php
/**
 * PSR-4 简易自动加载器
 */

namespace RateLimit;

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(function ($class) {
            $prefix = 'RateLimit\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
