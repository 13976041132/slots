<?php
/**
 * 订阅/发布服务
 */

namespace FF\Service;

use FF\Factory\MQ;
use FF\Framework\Utils\Log;
use FF\Service\Lib\Service;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Process;

class PubSubServer extends Service
{
    protected $subProcessId;

    protected $exchange = '';
    protected $exchangeOpt = array();
    protected $queueName = '';
    protected $queueOpt = array();

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        $this->createSubProcess();
    }

    public function onWorkerStop(\swoole_server $server, $workerId)
    {
        $this->killSubProcess();

        parent::onWorkerStop($server, $workerId);
    }

    public function onReload(\swoole_server $server, int $workerId)
    {
        $this->killSubProcess();

        $this->createSubProcess();
    }

    public function createSubProcess()
    {
        $process = new Process(array($this, 'pubSubMain'));

        $this->subProcessId = $process->start();
    }

    public function killSubProcess()
    {
        if ($this->subProcessId) {
            Process::kill($this->subProcessId, SIGKILL);
            Log::info("sub process killed, pid = {$this->subProcessId}");
        }
    }

    public function pubSubMain(Process $process)
    {
        Log::info('subprocess started, pid = ' . $process->pid);

        $this->subProcessId = $process->pid;

        $mq = MQ::rabbitmq();
        $mq->setAutoReConnect(true);
        if ($this->exchange !== '') {
            $mq->declareExchange($this->exchange, $this->exchangeOpt);
        }
        $mq->declareQueue($this->exchange, $this->queueName, $this->queueOpt);
        $mq->consume($this->queueName, array($this, 'onChannelMessage'), array('no_ack' => true));
    }

    /**
     * @param AMQPMessage $amqpMessage
     */
    public function onChannelMessage($amqpMessage)
    {
        $message = $amqpMessage->getBody();

        Log::info("onChannelMessage, pid = {$this->subProcessId} message = " . $message);

        $data = json_decode($message, true);

        if (is_null($data)) $data = $message;

        try {
            $this->dealMessage($data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function dealMessage($data)
    {
        //to override
    }
}