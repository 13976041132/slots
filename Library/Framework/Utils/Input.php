<?php
/**
 * 输入管理器
 */

namespace FF\Framework\Utils;

class Input
{
    /**
     * 从输入池中获取一项数据
     * @param array $inputs
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function fetch($inputs, $key, $default = '')
    {
        return isset($inputs[$key]) ? $inputs[$key] : $default;
    }

    /**
     * 获取GET参数
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get($key, $default = '')
    {
        return self::fetch($_GET, $key, $default);
    }

    /**
     * 获取POST参数
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function post($key, $default = '')
    {
        return self::fetch($_POST, $key, $default);
    }

    /**
     * 获取GET|POST参数
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function request($key, $default = '')
    {
        return self::fetch($_REQUEST, $key, $default);
    }

    /**
     * 获取COOKIE
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function cookie($key, $default = '')
    {
        return self::fetch($_COOKIE, $key, $default);
    }
}