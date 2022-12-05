<?php
/**
 * 配置管理器
 */

namespace FF\Framework\Utils;

use FF\Factory\Bll;
use FF\Framework\Common\Env;
use FF\Framework\Core\FF;
use FF\Framework\Common\Code;

class Config
{
    private static $config = array();

    /**
     * @var callable $nameParser
     */
    private static $nameParser = null;

    /**
     * 获取默认配置名称解析器
     * 默认格式：path/name/key1:val1/key2:val2
     * @return \Closure
     */
    public static function getDefaultNameParser()
    {
        return function ($name) {
            if (!is_string($name)) return null;
            if ($name[0] == '/') $name = substr($name, 1);
            if ($name === '') return null;
            $path = '';
            $_name = '';
            $params = array();
            $nameFields = explode('/', $name);
            while (1) {
                $field = array_pop($nameFields);
                if ($field === '') return null;
                $fieldInfo = explode(':', $field);
                if (count($fieldInfo) == 2) {
                    $params[$fieldInfo[0]] = $fieldInfo[1];
                } else {
                    $_name = $field;
                    break;
                }
                if (!$nameFields) {
                    break;
                }
            }
            if ($_name === '') return null;
            if ($nameFields) {
                $path = implode('/', $nameFields);
            }
            return array(
                'name' => $_name, 'path' => $path, 'params' => $params
            );
        };
    }

    /**
     * 获取配置名称解析器
     */
    public static function getNameParser()
    {
        if (!self::$nameParser) {
            self::$nameParser = self::getDefaultNameParser();
        }

        return self::$nameParser;
    }

    /**
     * 设置配置名称解析器
     */
    public static function setNameParser($parser)
    {
        self::$nameParser = $parser;
    }

    /**
     * 获取配置目录列表
     * 优先顺序：PATH_APP/Config/ENV > PATH_APP/Config > PATH_ROOT/Config/ENV > PATH_ROOT/Config
     * @return array
     */
    private static function getPaths()
    {
        $paths = array();

        if (defined('ENV')) {
            $paths[] = PATH_APP . '/Config/' . ENV;
        }
        $paths[] = PATH_APP . '/Config';

        if (PATH_APP != PATH_ROOT) {
            if (defined('ENV')) {
                $paths[] = PATH_ROOT . '/Config/' . ENV;
            }
            $paths[] = PATH_ROOT . '/Config';
        }

        return $paths;
    }

    /**
     * 加载配置
     * @param $name
     * @param bool $require 是否必须有该配置
     * @return mixed
     */
    public static function load($name, $require = true)
    {
        $nameParser = self::getNameParser();
        $nameInfo = call_user_func($nameParser, $name);
        if (!$nameInfo) {
            $message = "Config {$name} parse error";
            FF::throwException(Code::PARAMS_INVALID, $message);
        }

        $realName = $nameInfo['name'];
        if (!is_empty($nameInfo['path'])) {
            $realName = $nameInfo['path'] . '/' . $realName;
        }
        if (isset(self::$config[$realName])) {
            return self::$config[$realName];
        }

        //Log::info([$name, $nameInfo['name']], 'config.log');

        $config = null;
        $paths = self::getPaths();

        foreach ($paths as $path) {
            $config = file_include($path . "/{$realName}.php");
            if ($config) break;
        }

        if ($config === null) {
            if ($require) {
                $message = "Config {$realName} is missed";
                FF::throwException(Code::CONFIG_MISSED, $message);
            } else {
                return null;
            }
        }

        self::$config[$realName] = $config;

        return $config;
    }

    /**
     * 获取配置内容
     * @param string $name 配置名称
     * @param string|bool $key 下级配置，支持多级，斜线"/"分隔
     * @param bool $require 是否必须有该配置
     * @return mixed
     */
    public static function get($name, $key = '', $require = true)
    {
        //支持参数缩减
        if (is_bool($key)) {
            $require = $key;
            $key = '';
        }

        $config = self::load($name, $require);
        if (!$config) return [];

        $config = self::getDefault($config, $name, $key, $require);

        return $config;
    }

    /**
     * 获取配置内容
     * @param string $name 配置名称
     * @param string|bool $key 下级配置，支持多级，斜线"/"分隔
     * @param bool $require 是否必须有该配置
     * @return mixed
     */
    public static function getDefault($config, $name, $key = '', $require = true)
    {
        //支持直接获取下级配置
        //支持多级，斜线"/"分隔
        if (!is_empty($key)) {
            $nodes = explode('/', $key);
            foreach ($nodes as $node) {
                if (!is_empty($node) && is_array($config) && isset($config[$node])) {
                    $config = $config[$node];
                } else {
                    return null;
                }
            }
        }

        return $config;
    }

    /**
     * 设置配置
     * @param string $name
     * @param string $key 下级配置，支持多级，斜线"/"分隔
     * @param mixed $data
     */
    public static function set($name, $key, $data)
    {
        if (!is_empty($key)) {
            $nodes = explode('/', $key);
            if (count($nodes) > 1) {
                $nodes = array_reverse($nodes);
            }
            foreach ($nodes as $node) {
                if (!is_empty($node)) {
                    $data = array($node => $data);
                }
            }
            $config = self::load($name, false);
            if (is_array($config)) {
                $config = array_merge_recursive($config, $data);
            } else {
                $config = $data;
            }
        } else {
            $config = $data;
        }

        self::$config[$name] = $config;
    }

    /**
     * 清空配置
     */
    public static function clear()
    {
        self::$config = array();
    }
}