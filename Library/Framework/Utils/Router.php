<?php

namespace FF\Framework\Utils;
class Router
{
    private static $routes = [];
    private static $middlewares = [];

    // 注册中间件
    public static function middleware(array $middlewares)
    {
        self::$middlewares[] = $middlewares;
        return new self();
    }

    // 路由分组
    public static function group($routes)
    {
        $size = count(self::$middlewares);
        $middlewares = $size ? self::$middlewares[$size - 1] : [];
        foreach ($routes as $messageId => $route) {
            self::$routes[$messageId] = [
                'route' => $route[0] ?? $route,
                'middlewares' => $middlewares,
            ];
        }
    }

    public static function getRouteByMsgId($msgId)
    {
        foreach (self::$routes as $messageId => $info) {
            if ($messageId == $msgId) {
                return $info;
            }
        }
        return [];
    }
}

