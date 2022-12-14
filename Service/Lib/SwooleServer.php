<?php
/**
 * 服务模型
 * 支持Tcp/Udp
 * 基于swoole
 */

namespace FF\Service\Lib;

use FF\Framework\Utils\Log;

class SwooleServer
{
    protected $host;
    protected $port;
    protected $server_mode;
    protected $sock_type;
    protected $options;
    protected $buffer = array();

    /**
     * @var
     */
    protected $server;

    public function __construct($options)
    {
        $this->host = isset($options['host']) ? $options['host'] : '0.0.0.0';
        $this->port = isset($options['port']) ? $options['port'] : 0;
        $this->server_mode = isset($options['mode']) ? $options['mode'] : SWOOLE_PROCESS;
        $this->sock_type = isset($options['sock']) ? $options['sock'] : SWOOLE_SOCK_TCP;
        $this->options = isset($options['options']) ? $options['options'] : array();

        $this->init();
    }

    private function init()
    {
        $this->server = new \swoole_server('0.0.0.0', $this->port, $this->server_mode, $this->sock_type);

        $this->server->set($this->options);

        $receiveEvent = $this->sock_type === SWOOLE_SOCK_TCP ? 'Receive' : 'Packet';
        $receiveHandle = $this->sock_type === SWOOLE_SOCK_TCP ? 'onReceive' : 'onPacket';

        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->server->on('WorkerStop', array($this, 'onWorkerStop'));
        $this->server->on('Connect', array($this, 'onConnect'));
        $this->server->on('Close', array($this, 'onClose'));
        $this->server->on($receiveEvent, array($this, $receiveHandle));
        $this->server->on('Task', array($this, 'onTask'));
        $this->server->on('Finish', array($this, 'onFinish'));
    }

    public function start()
    {
        echo get_called_class() . " started on {$this->host}:{$this->port}" . PHP_EOL;

        $this->server->start();
    }

    public function onWorkerStop(swoole_server $server, $workerId)
    {

    }

    public function onWorkerStart(\swoole_server $server, $worker_id)
    {
        echo get_called_class() . ' worker ' . $worker_id . ' started' . PHP_EOL;

        //do something after worker started
        //to override
    }

    public function onConnect(\swoole_server $server, int $fd, int $reactor_id)
    {
        //echo "new connection, fd=$fd, reactor_id=$reactor_id" . PHP_EOL;
    }

    public function onClose(\swoole_server $server, int $fd, int $reactor_id)
    {
        //echo "connection closed, fd=$fd, reactor_id=$reactor_id" . PHP_EOL;

        if (isset($this->buffer[$fd])) {
            if ($this->buffer[$fd]) echo 'buffer=' . $this->buffer[$fd] . PHP_EOL;
            unset($this->buffer[$fd]);
        }
    }

    public function onReceive(\swoole_server $server, $fd, $reactor_id, $data)
    {
        //echo "onReceive: fd=$fd, reactor_id=$reactor_id, data=$data" . PHP_EOL;

        $this->onMessage($fd, $data);
    }

    public function onPacket(\swoole_server $server, $data, $client_info)
    {
        //echo "onPacket: data=$data, client_info=" . json_encode($client_info) . PHP_EOL;

        $this->onMessage(null, $data);
    }

    public function onTask(\swoole_server $server, $task_id, $reactor_id, $data)
    {
        //echo "New Task: work_id={$server->worker_id}, task_id={$task_id}, reactor_id={$reactor_id}, data={$data}" . PHP_EOL;

        try {
            return $this->dealTask($data);
        } catch (\Exception $e) {
            Log::error(array('code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTrace()));
            return 'fail';
        }
    }

    public function onFinish(\swoole_server $server, $task_id, $data)
    {
        //echo "Task finished: work_id={$server->worker_id}, task_id={$task_id}, data={$data}" . PHP_EOL;

        if ($data != 'ok') echo $data . PHP_EOL;
    }

    private function onStop()
    {
        $this->server->shutdown();
    }

    private function onReload()
    {
        $this->server->reload();
    }

    private function onRestart()
    {
        $this->server->reload(true);
    }

    private function onMessage($fd, $data)
    {
        $packages = $this->getPackages($fd, $data);

        if (!$packages) return;

        for ($i = 0; $i < count($packages); $i++) {
            $data = $this->parsePacket($packages[$i]);
            if (!$data || !isset($data['event'])) {
                echo $packages[$i] . PHP_EOL;
                continue;
            }
            $this->dispatchMessage($fd, $data);
        }
    }

    private function dispatchMessage($fd, $data)
    {
        if (in_array($data['event'], ['Stop', 'Reload', 'Restart', 'Stats'])) {
            $clientInfo = $this->server->getClientInfo($fd);
            if (!$clientInfo) return;
            $remoteIp = $clientInfo['remote_ip'];
            if ($remoteIp != '127.0.0.1' && substr($remoteIp, 0, 7) != '192.168') {
                Log::warning("A danger command [{$data['event']}] from {$remoteIp}");
                //return;
            }
            switch ($data['event']) {
                case 'Stop':
                    $this->onStop();
                    break;
                case 'Reload':
                    $this->onReload();
                    break;
                case 'Restart':
                    $this->onRestart();
                    break;
                case 'stats':
                    $stats = $this->server->stats();
                    $stats = array_merge(array(
                        'name' => $this->server->serverName,
                        'host' => $this->host,
                        'port' => $this->port,
                    ), $stats);
                    $this->server->sendMessage($fd, json_encode($stats));
                    break;
            }
        } else {
            $taskWorkerId = 0;
            if (isset($data['data']['uid'])) {
                $taskWorkerId = $data['data']['uid'] % $this->options['task_worker_num'];
            }
            $task_id = $this->server->task($data, $taskWorkerId);
            if ($task_id === false) {
                echo 'dispatch task failed' . PHP_EOL;
                print_r($this->server->stats());
            }
        }
    }

    private function getPackages($fd, $data)
    {
        $flag_begin = '--BEGIN--';
        $flag_end = '--END--';

        //TCP包可能存在分包、粘包
        //UPD包具有天然隔离性
        if ($this->sock_type == SWOOLE_SOCK_TCP) {
            if (mb_substr($data, 0, mb_strlen($flag_begin)) == $flag_begin) {
                if (mb_substr($data, -1 * mb_strlen($flag_end)) != $flag_end) {
                    $pos = mb_strrpos($data, $flag_begin);
                    if ($pos > 0) {
                        $this->buffer[$fd] = mb_substr($data, $pos);
                        $data = mb_substr($data, 0, $pos);
                    } else {
                        $this->buffer[$fd] = $data;
                        return null;
                    }
                }
            } else if (!empty($this->buffer[$fd])) {
                if (mb_substr($data, -1 * mb_strlen($flag_end)) != $flag_end) {
                    $this->buffer[$fd] .= $data;
                    return null;
                } else {
                    $data = $this->buffer[$fd] . $data;
                    unset($this->buffer[$fd]);
                }
            } else {
                return null;
            }
        }

        $data = mb_substr($data, mb_strlen($flag_begin), -1 * mb_strlen($flag_end));
        $packages = explode($flag_end . $flag_begin, $data);

        return $packages;
    }

    protected function parsePacket($data)
    {
        return json_decode($data, true);
    }

    protected function getUserId($data)
    {
        return isset($data['uid']) ? $data['uid'] : 0;
    }

    protected function onUserBind($fd, $uid)
    {
        //to override
        //do something after user bind
    }

    protected function dealTask($data)
    {
        return 'ok';
    }
}