<?php

namespace FF\Middleware;

use FF\Constants\Exceptions;
use FF\Framework\Core\FF;

class Kernel
{
    protected static  $routeMiddleware = [
        'checkSignature' => CheckSignature::class,
    ];

    public static function getMiddleware($name) {
            if (isset(self::$routeMiddleware[$name])) {
            return self::$routeMiddleware[$name];
        }
        FF::throwException(Exceptions::MIDDLEWARE_NOT_EXIST);
    }
}