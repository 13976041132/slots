<?php
/**
 * SwooleClient
 */

namespace FF\Service\Lib;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

class SwooleClient
{
    private $client = null;
    private $serverType = '';
    private $serverNode = null;

    public function __construct($serverType, $serverNode = null)
    {
        $this->serverType = $serverType;
        $this->serverNode = $serverNode;
    }

    public function enabled()
    {
        if (!class_exists('swoole_client')) return false;

        $client = $this->client();

        if (!$client) return false;

        return $client->isConnected();
    }

    private function client()
    {
        if (!isset($this->client)) {
            $node = $this->serverNode;
            $config = Config::get('servers', $this->serverType, false);
            if (!$config) return null;
            if ($node === null) $node = array_rand($config['nodes']);
            if (!isset($config['nodes'][$node])) FF::throwException(Code::SYSTEM_ERROR);
            $sock_type = isset($config['sock']) ? $config['sock'] : SWOOLE_SOCK_TCP;
            $client = new \swoole_client($sock_type, SWOOLE_SOCK_SYNC);
            if ($sock_type == SWOOLE_SOCK_TCP && !is_cli()) {
                $client->set(array('open_tcp_nodelay' => true));
            }
            $host = $config['nodes'][$node]['host'];
            $port = $config['nodes'][$node]['port'];
            $flag = @$client->connect($host, $port, 0.01);
            if (!$flag) {
                Log::error("swoole server connected failed at {$host}:{$port}");
            }
            $this->client = $client;
        }

        return $this->client;
    }

    public function send($event, $data = array())
    {
        if (!$this->enabled()) return;

        $data = array('event' => $event, 'data' => $data);
        $data = '--BEGIN--' . json_encode($data) . '--END--';

        $result = $this->client()->send($data);
        if (!$result) {
            Log::error('send data to server failed, error = ' . $this->client()->errCode);
        }
    }

    public function receive()
    {
        if (!$this->enabled()) return false;

        $data = $this->client()->recv();

        if ($data == '') return false;

        $data = rtrim(ltrim($data, '--BEGIN--'), '--END--');

        return json_decode($data, true);
    }
}