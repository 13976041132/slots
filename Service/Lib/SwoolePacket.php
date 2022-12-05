<?php
/**
 * Swoole消息包
 */

namespace FF\Service\Lib;

use FF\Framework\Common\Format;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

class SwoolePacket
{
    public static function packMessage($version, $seq, $msgId, $code, $data, $format = '', $isResponse = false)
    {
        $content = self::protoPackMessage($msgId, $data, $format, $isResponse);

        if ($version == 0) {
            return self::packMessageV0($msgId, $code, $content, $isResponse);
        } else {
            return self::packMessageV1($msgId, $code, $content, $version, $seq);
        }
    }

    public static function unpackPacket($data, $version, $format = '', $isResponse = false)
    {
        if (!$data) return null;

        $length = unpack('N', substr($data, 0, 4))[1];
        if (!$length) return null;

        if ($version == 0) {
            $package = self::unpackPacketV0($data, $isResponse);
        } else {
            $package = self::unpackPacketV1($data);
        }

        if (!$package['code']) {
            try {
                $result = self::parseMessage($package['msgId'], $package['message'], $format, $isResponse);
                $package = array_merge($package, $result);
            } catch (\Exception $e) {
                Log::error([$version, $package['msgId']]);
            }
        }

        unset($package['message']);

        return $package;
    }

    public static function packMessageV0($msgId, $code, $content, $isResponse)
    {
        $body = '';
        $body .= pack('n', $msgId);
        if ($code || $isResponse) $body .= pack('n', $code);
        $body .= $content;

        $length = pack('N', strlen($body));

        return $length . $body;
    }

    public static function packMessageV1($msgId, $code, $content, $version, $seq)
    {
        $body = '';
        $body .= pack('n', $version);
        $body .= pack('J', _microtime());
        $body .= pack('n', $seq);
        $body .= pack('n', $code ?: 0);
        $body .= pack('n', $msgId);
        $body .= $content;

        $length = pack('N', strlen($body));

        return $length . $body;
    }

    public static function unpackPacketV0($data, $isResponse = false)
    {
        if ($isResponse) {
            $msgId = unpack('n', substr($data, 4, 2))[1];
            $code = unpack('n', substr($data, 6, 2))[1];
            $message = substr($data, 8);
        } else {
            $msgId = unpack('n', substr($data, 4, 2))[1];
            $message = substr($data, 6);
            $code = 0;
        }

        return array(
            'version' => 0,
            'time' => 0,
            'seq' => 0,
            'msgId' => $msgId,
            'code' => $code,
            'message' => $message
        );
    }

    public static function unpackPacketV1($data)
    {
        $version = unpack('n', substr($data, 4, 2))[1];
        $time = unpack('J', substr($data, 6, 8))[1];
        $seq = unpack('n', substr($data, 14, 2))[1];
        $code = unpack('n', substr($data, 16, 2))[1];
        $msgId = unpack('n', substr($data, 18, 2))[1];
        $message = substr($data, 20);

        return array(
            'version' => $version,
            'time' => $time,
            'seq' => $seq,
            'msgId' => $msgId,
            'code' => $code,
            'message' => $message
        );
    }

    public static function parseMessage($msgId, $message, $format = '', $isResponse = false)
    {
        $routeCfg = Config::get('routes', $msgId);
        if (!$routeCfg) return null;

        $proto = $routeCfg[1];
        if ($proto && substr($proto, 0, 2) == 'PB' && (!$format || $format == Format::PBUF)) {
            $protoClass = "\\GPBClass\\Message\\{$proto}_" . ($isResponse ? 'Res' : 'Req');
            /**@var $pbObject \Google\Protobuf\Internal\Message */
            $pbObject = new $protoClass();
            $pbObject->mergeFromString($message);
            $jsonData = $pbObject->serializeToJsonString();
            $data = json_decode($jsonData, true);
        } else {
            $data = json_decode($message, true);
        }

        return array(
            'route' => $routeCfg[0],
            'data' => $data
        );
    }

    public static function protoPackMessage($msgId, $data, $format = '', $isResponse = false)
    {
        if ($data) {
            $routeCfg = Config::get('routes', $msgId);
            if (!$routeCfg) return false;
            $proto = $routeCfg[1];
            if ($proto && substr($proto, 0, 2) == 'PB' && (!$format || $format == Format::PBUF)) {
                $protoClass = "\\GPBClass\\Message\\{$proto}_" . ($isResponse ? 'Res' : 'Req');
                /**@var $pbObject \Google\Protobuf\Internal\Message */
                $pbObject = new $protoClass();
                Log::info(['protoPackMessage', $msgId, $protoClass, json_encode($data), $data], 'adfas.log');
                $pbObject->mergeFromJsonString(json_encode($data));
                $content = $pbObject->serializeToString();
            } else {
                $content = is_array($data) ? json_encode($data) : $data;
            }
        } else {
            $content = '';
        }
        return $content;
    }
}