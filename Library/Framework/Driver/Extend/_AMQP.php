<?php
/**
 * AMQP操作类
 * based on rabbitmq
 */

namespace FF\Framework\Driver\Extend;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use FF\Framework\Utils\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

file_require(PATH_LIB . '/Vendor/AMQP/autoload.php', true);

class _AMQP
{
    private $config;
    private $connection;
    private $autoReConnect = false;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->connection) {
            $this->connection->close();
        }

        $this->connection = null;
    }

    public function setAutoReConnect(bool $autoReConnect)
    {
        $this->autoReConnect = $autoReConnect;
    }

    protected function getConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }

        $host = $this->config['host'];
        $port = $this->config['port'];
        $user = $this->config['user'];
        $pass = $this->config['pass'];
        $vhost = $this->config['vhost'] ?: '/';
        $insist = $this->config['insist'] ?? false;
        $login_method = $this->config['login_method'] ?? 'AMQPLAIN';
        $login_response = $this->config['login_response'] ?? null;
        $locale = $this->config['locale'] ?? 'en_US';
        $connection_timeout = $this->config['connection_timeout'] ?? 3.0;
        $read_write_timeout = $this->config['read_write_timeout'] ?? 3.0;
        $context = $this->config['context'] ?? null;
        $keepalive = $this->config['keepalive'] ?? true;
        $heartbeat = $this->config['heartbeat'] ?? 0;
        $channel_rpc_timeout = $this->config['channel_rpc_timeout'] ?? 3.0;
        $ssl_protocol = $this->config['ssl_protocol'] ?? null;

        try {
            $this->connection = new AMQPStreamConnection(
                $host, $port, $user, $pass, $vhost, $insist,
                $login_method, $login_response, $locale, $connection_timeout, $read_write_timeout,
                $context, $keepalive, $heartbeat, $channel_rpc_timeout, $ssl_protocol
            );
            Log::info('connected', 'mq.log');
            return $this->connection;
        } catch (\Exception $e) {
            Log::error(array('code' => $e->getCode(), 'message' => $e->getMessage()), 'mq.log');
            if ($this->autoReConnect) {
                sleep(1);
                Log::info('try to reconnect in connecting', 'mq.log');
                return $this->getConnection();
            } else {
                Log::error('unable to connect server,check server run enable', 'mq.log');
                FF::throwException(Code::SYSTEM_BUSY);
            }
        }
    }

    protected function getChannel($channelId)
    {
        $channel = $this->getConnection()->channel($channelId);

        return $channel;
    }

    public function declareExchange($exchange, $options = array())
    {
        $channelId = $options['channel_id'] ?? null;
        $exchangeType = $options['exchange_type'] ?? AMQPExchangeType::DIRECT;
        $passive = $options['passive'] ?? false;
        $durable = $options['durable'] ?? true;
        $autoDelete = $options['auto_delete'] ?? false;
        $internal = $options['internal'] ?? false;
        $nowait = $options['nowait'] ?? false;

        $channel = $this->getChannel($channelId);
        $channel->exchange_declare($exchange, $exchangeType, $passive, $durable, $autoDelete, $internal, $nowait);
    }

    public function declareQueue($exchange, $queue, $options = array())
    {
        $channelId = $options['channel_id'] ?? null;
        $routingKey = $options['routing_key'] ?? '';
        $passive = $options['passive'] ?? false;
        $durable = $options['durable'] ?? true;
        $exclusive = $options['exclusive'] ?? false;
        $autoDelete = $options['auto_delete'] ?? false;
        $nowait = $options['nowait'] ?? false;

        $channel = $this->getChannel($channelId);
        $channel->queue_declare($queue, $passive, $durable, $exclusive, $autoDelete, $nowait);
        if ($exchange !== '') {
            $channel->queue_bind($queue, $exchange, $routingKey, $nowait);
        }
    }

    public function publish($exchange, $message, $options = array())
    {
        $channelId = $options['channel_id'] ?? null;
        $routingKey = $options['routing_key'] ?? '';
        $contentType = $options['content_type'] ?? 'text/plain';
        $deliveryMode = $options['delivery_mode'] ?? AMQPMessage::DELIVERY_MODE_PERSISTENT;

        $channel = $this->getChannel($channelId);

        $amqpMessage = new AMQPMessage($message, array(
            'content_type' => $contentType,
            'delivery_mode' => $deliveryMode
        ));

        $channel->basic_publish($amqpMessage, $exchange, $routingKey);
        $channel->close();
    }

    public function consume($queue, $callback, $options = array())
    {
        try {
            $this->consuming($queue, $callback, $options);
        } catch (\Exception $e) {
            Log::error(array('code' => $e->getCode(), 'message' => $e->getMessage()), 'mq.log');
            if ($this->isConnectError($e)) {
                if ($this->autoReConnect) {
                    Log::info('try to reconnect in consuming', 'mq.log');
                    $this->connection = null;
                    $this->consume($queue, $callback, $options);
                }
            } else {
                throw $e;
            }
        }
    }

    private function consuming($queue, $callback, $options = array())
    {
        $channelId = $options['channel_id'] ?? null;
        $consumerTag = $options['consumer_tag'] ?? '';
        $noLocal = $options['no_local'] ?? false;
        $noAck = $options['no_ack'] ?? false;
        $exclusive = $options['exclusive'] ?? false;
        $nowait = $options['nowait'] ?? false;

        $channel = $this->getChannel($channelId);
        $channel->basic_consume($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    private function isConnectError(\Exception $e)
    {
        if ($e instanceof AMQPChannelClosedException) {
            return true;
        }

        return Str::contains(strtolower($e->getMessage()), [
            'broken pipe',
            'closed connection',
            'connection reset by peer',
            'broker forced connection',
            'missed server heartbeat'
        ]);
    }
}