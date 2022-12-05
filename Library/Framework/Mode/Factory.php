<?php
/**
 * 工厂类
 */

namespace FF\Framework\Mode;

use FF\Framework\Common\Code;

class Factory
{
    /**
     * 是否是单例模式
     * @var bool
     */
    protected $singleMode = true;

    /**
     * 对象实例缓存
     * @var array
     */
    private $instances = array();

    /**
     * 工厂实例缓存
     * @var array
     */
    private static $factories = array();

    /**
     * 获取类实例
     * @param string $class 模型类名
     * @param string | null $identify 模型标识
     * @param array $args 实例化构造参数
     * @return mixed
     * @throws \Exception
     */
    public static function getInstance($class, $identify = null, $args = array())
    {
        $factory = get_called_class();

        return self::getFactory($factory)->createInstance($class, $identify, $args);
    }

    /**
     * 销毁类实例
     * @param $class
     * @param $identify
     */
    public static function delInstance($class, $identify)
    {
        $factory = get_called_class();

        self::getFactory($factory)->deleteInstance($class, $identify);
    }

    /**
     * 获取工厂实例
     * @return Factory
     */
    protected static function getFactory($factory)
    {
        if (!isset(self::$factories[$factory])) {
            self::$factories[$factory] = new $factory();
        }

        return self::$factories[$factory];
    }

    /**
     * 创建类实例
     * @param $class
     * @param null $identify
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    protected function createInstance($class, $identify = null, $args = array())
    {
        if (is_empty($identify)) {
            $identify = $class;
        }
        if (!isset($this->instances[$class])) {
            $this->instances[$class] = array();
        }
        if ($this->singleMode && isset($this->instances[$class][$identify])) {
            return $this->instances[$class][$identify];
        }
        if (!class_exists($class, true)) {
            throw new \Exception("Load class $class failed", Code::CLASS_NOT_EXIST);
        }

        $instance = new $class(...$args);

        if (method_exists($instance, 'setClassAndIdentify')) {
            $instance->setClassAndIdentify($class, $identify);
        }

        $this->instances[$class][$identify] = $instance;

        return $instance;
    }

    /**
     * 销毁实例
     * @param $class
     * @param $identify
     */
    protected function deleteInstance($class, $identify)
    {
        if (isset($this->instances[$class][$identify])) {
            unset($this->instances[$class][$identify]);
        }
    }
}