<?php
/**
 * ./tests/test.sh
 * OR
 * phpunit6.phar --bootstrap tests/boot.php tests
 */

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(function($class)
{
    $prefix = 'SimpleAR\\';

    // e.g. "Inhere\Validate\ValidationTrait"
    if (strpos($class, $prefix) !== false) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))). '.php';
//    } else {
//        $file = __DIR__ . '/' . $class. '.php';
    }

    if (is_file($file)) {
        include $file;
    }
});
