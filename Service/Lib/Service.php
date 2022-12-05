<?php
/**
 * 服务模型
 */

namespace FF\Service\Lib;

class Service extends SwooleServer
{
    protected static $instance = null;

    public static function getInstance($options)
    {
        if (!self::$instance) {
            $class = get_called_class();
            self::$instance = new $class($options);
        }

        return self::$instance;
    }

    public static function isRunning()
    {
        return self::$instance ? true : false;
    }
}