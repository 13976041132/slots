<?php
/**
 * 消息队列对象工厂
 */

namespace FF\Factory;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Driver\Extend\_AMQP;
use FF\Framework\Mode\Factory;
use FF\Framework\Utils\Config;

class MQ extends Factory
{
    /**
     * @param $name
     * @return _AMQP
     */
    public static function rabbitmq($name = 'main')
    {
        $config = Config::get('rabbitmq', $name);

        if (!$config) {
            FF::throwException(Code::CONFIG_MISSED);
        }

        return self::getInstance('FF\Framework\Driver\Extend\_AMQP', $name, [$config]);
    }
}