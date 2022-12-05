<?php
/**
 * redis操作类
 */

namespace FF\Framework\Driver\Extend;

use FF\Framework\Common\Code;

class _Redis
{
    /**
     * @var \Redis
     */
    private $redis;

    private $options;

    public function __construct($options)
    {
        $this->options = $options;

        $this->connect();
    }

    protected function connect()
    {
        if (isset($this->options['cluster'])) {
            $this->connectCluster();
        } else {
            $this->connectServer();
        }
    }

    protected function connectCluster()
    {
        if (!class_exists('RedisCluster', false)) {
            return;
        }

        $this->redis = new \RedisCluster(null, $this->options['cluster']);
    }

    protected function connectServer()
    {
        if (!class_exists('Redis', false)) {
            return;
        }

        $host = $this->options['host'];
        $port = $this->options['port'];
        $auth = $this->options['auth'];

        $redis = new \Redis();
        $connected = $redis->pconnect($host, $port, 0);

        if (!$connected) {
            throw new \Exception('Failed to connect redis [' . $host . ':' . $port . ']', Code::REDIS_CONNECT_FAILED);
        }
        if ($auth && !$redis->auth($auth)) {
            throw new \Exception('Failed to auth redis connection', Code::REDIS_AUTH_FAILED);
        }

        $this->redis = $redis;
    }

    public function __call($method, $args)
    {
        if (!$this->redis) {
            throw new \Exception('Has not connect to redis', Code::FAILED);
        }

        return call_user_func_array(array($this->redis, $method), $args);
    }
}