<?php
/**
 * 数据访问对象工厂
 */

namespace FF\Factory;

use FF\Framework\Driver\Extend\_Pdo;
use FF\Framework\Mode\Factory;
use FF\Framework\Utils\Config;

class Dao extends Factory
{
    /**
     * @param $name
     * @return _Pdo
     */
    public static function db($name = DB_MAIN)
    {
        $config = Config::get('database', $name);
        $identify = md5("{$config['host']}:{$config['port']}:{$config['dbname']}:{$config['username']}");
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";

        $options = isset($config['options']) ? $config['options'] : array();
        $options[\PDO::ATTR_PERSISTENT] = is_cli() ? true : ($config['persistent'] ?: false);
        if (!empty($config['ssl_ca'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $config['ssl_ca'];
        }

        $args = array($dsn, $config['username'], $config['passwd'], $options);

        return self::getInstance('FF\Framework\Driver\Extend\_Pdo', $identify, $args);
    }

    /**
     * @param $name
     * @return \Redis
     */
    public static function redis($name = 'main')
    {
        $config = Config::get('redis', $name);
        $identify = isset($config['cluster']) ? $name : ($config['host'] . ':' . $config['port']);

        return self::getInstance('FF\Framework\Driver\Extend\_Redis', $identify, [$config]);
    }

    /**
     * @param $name
     * @return \Memcached
     */
    public static function memcache($name = 'main')
    {
        $args = Config::get('memcache', $name);

        return self::getInstance('FF\Framework\Driver\Extend\_Memcached', $name, $args);
    }
}