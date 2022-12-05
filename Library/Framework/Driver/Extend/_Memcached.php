<?php
/**
 * memcache操作类
 * 基于memcached，支持集群
 */

namespace FF\Framework\Driver\Extend;

class _Memcached
{
    private $servers;
    private $memcache;

    public function __construct($servers)
    {
        $this->servers = $servers;
    }

    private function getPersistentId($servers)
    {
        $strings = [];
        foreach ($servers as $server) {
            $strings[] = implode('-', $server);
        }
        sort($strings);
        $string = implode('|', $strings);
        return md5($string);
    }

    private function getOptions()
    {
        $options = array();
        $options[\Memcached::OPT_CONNECT_TIMEOUT] = 500;
        $options[\Memcached::OPT_NO_BLOCK] = true;
        $options[\Memcached::OPT_TCP_NODELAY] = true;
        $options[\Memcached::OPT_HASH] = \Memcached::HASH_CRC;
        $options[\Memcached::OPT_DISTRIBUTION] = \Memcached::DISTRIBUTION_CONSISTENT;
        $options[\Memcached::OPT_LIBKETAMA_COMPATIBLE] = true;
        $options[\Memcached::OPT_BINARY_PROTOCOL] = true;
        return $options;
    }

    public function getInstance()
    {
        if (!$this->memcache) {
            $persistent_id = $this->getPersistentId($this->servers);
            $this->memcache = new \Memcached($persistent_id);
            if (!count($this->memcache->getServerList())) {
                $this->memcache->addServers($this->servers);
                $this->memcache->setOptions($this->getOptions());
            }
        }

        return $this->memcache;
    }

    public function set($key, $value, $expiration = null)
    {
        $expiration = $expiration ? (time() + $expiration) : null;

        $this->getInstance()->set($key, $value, $expiration);
    }

    public function __call($method, $args)
    {
        if (!class_exists('Memcached', false)) {
            return false;
        }
        return call_user_func_array(array($this->getInstance(), $method), $args);
    }
}