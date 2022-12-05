<?php
/**
 * RPC
 */

namespace FF\Service\Lib;

use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

class RPC
{
    private static $clients = array();

    public static function client($serverType, &$node = null, $reconnect = false)
    {
        if ($node === null) {
            $serverCfg = Config::get('servers', $serverType);
            if (!$serverCfg || empty($serverCfg['nodes'])) return null;
            $node = array_rand($serverCfg['nodes']);
        }

        if (isset(self::$clients[$serverType][$node])) {
            /** @var $client SwooleClient */
            $client = self::$clients[$serverType][$node];
            if ($reconnect) {
                $client->close();
            } else {
                return $client;
            }
        }

        $client = new SwooleClient($serverType, $node);

        self::$clients[$serverType][$node] = $client;

        return $client;
    }

    public static function close($serverType, $node)
    {
        if (!isset(self::$clients[$serverType][$node])) {
            return;
        }

        /** @var $client SwooleClient */
        $client = self::$clients[$serverType][$node];
        $client->close();

        unset(self::$clients[$serverType][$node]);
    }

    public static function getServerNode($serverType, $host, $port)
    {
        $node = null;

        $serverCfg = Config::get('servers', $serverType);
        if (!$serverCfg || empty($serverCfg['nodes'])) return null;

        foreach ($serverCfg['nodes'] as $k => $nodeInfo) {
            if ($host == $nodeInfo['host'] && $port == $nodeInfo['port']) {
                $node = $k;
                break;
            }
        }

        return $node;
    }

    public static function request($serverType, $msgId, $message, $format = '', $async = false, $node = null)
    {
        Log::info("RPC request, serverType = {$serverType}, msgId = {$msgId}, message = " . json_encode($message), 'RPC.log');

        $client = self::client($serverType, $node);
        if (!$client) return null;

        $response = null;

        try {
            $result = $client->send($msgId, $message, $format);
            if (!$result) { //发送失败，重连/重试
                $client = self::client($serverType, $node, true);
                $result = $client->send($msgId, $message, $format);
            }
            if ($async) return null;
            if ($result) {
                $seq = $client->getSeq();
                $response = $client->receive($format);
                if ($response && $response['msgId'] != $msgId) {
                    self::close($serverType, $node);
                    Log::error('RPC error: ' . json_encode([$msgId, $seq, $message, $response]), 'RPC.log');
                    $response = null;
                } elseif ($response && $response['seq'] != $seq && $client->getProtocolVer() != 0) {
                    self::close($serverType, $node);
                    Log::error('RPC error: ' . json_encode([$msgId, $seq, $message, $response]), 'RPC.log');
                    $response = null;
                }
            }
        } catch (\Exception $e) {
            $error = array(
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            );
            Log::error('RPC error: ' . json_encode($error), 'RPC.log');
        }

        Log::info("RPC response, res = " . json_encode($response), 'RPC.log');

        return $response;
    }
}