<?php
/**
 * 请求解析器
 */

namespace FF\Library\Utils;

use FF\Framework\Core\FF;
use FF\Framework\Common\Code;
use FF\Framework\Common\Format;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;

class Request
{
    private static $msgId = null;
    private static $msgCfg = null;
    private static $format = null;
    private static $message = null;

    const REQ_KEY_MSG_ID = 'c';
    const REQ_KEY_MSG_CONTENT = 'k';
    const REQ_KEY_FORMAT = 'f';

    public static function getMsgId()
    {
        if (self::$msgId === null) {
            self::$msgId = (int)Input::request(self::REQ_KEY_MSG_ID, '0');
        }
        return self::$msgId;
    }

    public static function getMsgConfig()
    {
        if (self::$msgCfg === null) {
            $msgId = self::getMsgId();
            if (!$msgId) return null;
            $config = Config::get('routes', $msgId);
            if (!$config) {
                $message = "Config routes => {$msgId} is missed";
                FF::throwException(Code::CONFIG_MISSED, $message);
            }
            self::$msgCfg = $config;
        }
        return self::$msgCfg;
    }

    public static function getFormat()
    {
        if (self::$format === null) {
            self::$format = (string)Input::request(self::REQ_KEY_FORMAT, Format::JSON);
        }
        return self::$format;
    }

    public static function getRoute()
    {
        $config = self::getMsgConfig();
        return $config ? $config[0] : null;
    }

    public static function getProto()
    {
        $config = self::getMsgConfig();
        return $config ? $config[1] : null;
    }

    public static function getMessage()
    {
        if (self::$message === null) {
            self::$message = self::parseMessage();
        }
        return self::$message;
    }

    public static function parseMessage()
    {
        if (!self::getMsgId()) return $_REQUEST;

        $message = array();
        $format = self::getFormat();
        $content = (string)Input::request(self::REQ_KEY_MSG_CONTENT);

        if ($format == Format::JSON) {
            $message = $content ? json_decode($content, true) : array();
            if ($message === null) {
                FF::throwException(Code::PARAMS_INVALID, "Invalid json");
            }
        } elseif ($format == Format::PBUF) {
            $string = base64_decode($content);
            if ($string === false) {
                FF::throwException(Code::PARAMS_INVALID, "Invalid base64 string");
            }
            $message = self::parseProtoBuf($string);
        }

        return $message;
    }

    public static function serializeMessage($message)
    {
        $string = '';
        $format = self::getFormat();

        if ($format == Format::JSON) {
            $string = json_encode($message, JSON_UNESCAPED_UNICODE);
        } elseif ($format == Format::PBUF) {
            if ($message['data']) {
                if (!empty($message['data']['nextMessages'])) {
                    $message['data']['nextMessages'] = self::serializeNextMessages($message['data']['nextMessages']);
                }
                $message['data'] = self::serializeProtoBuf($message['data']);
                $message['data'] = base64_encode($message['data']);
            } else {
                $message['data'] = '';
            }
            $string = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        return $string;
    }

    public static function parseProtoBuf($string)
    {
        if (!$string) return array();

        $data = array();
        $proto = self::getProto();
        $pbClass = "\\GPBClass\\Message\\{$proto}_Req";

        try {
            /**@var $pbObject \Google\Protobuf\Internal\Message */
            $pbObject = new $pbClass();
            $pbObject->mergeFromString($string);
            $json = $pbObject->serializeToJsonString();
            $data = json_decode($json, true);
        } catch (\Exception $e) {
            FF::throwException(Code::PARAMS_INVALID, $e->getMessage());
        }

        return $data;
    }

    public static function serializeProtoBuf($data, $proto = null)
    {
        $string = '';
        $proto = $proto ?: self::getProto();
        $pbClass = "\\GPBClass\\Message\\{$proto}_Res";

        try {
            /**@var $pbObject \Google\Protobuf\Internal\Message */
            $pbObject = new $pbClass();
            $pbObject->mergeFromJsonString(json_encode($data));
            $string = $pbObject->serializeToString();
        } catch (\Exception $e) {
            FF::throwException(Code::PARAMS_INVALID, $e->getMessage());
        }

        return $string;
    }

    protected static function serializeNextMessages(array $nextMessages): array
    {
        $messages = [];
        foreach ($nextMessages as $msgId => $data) {
            $config = Config::get('routes', $msgId);
            if (!$config) {
                FF::throwException(Code::CONFIG_MISSED, "Config routes => {$msgId} is missed");
            }
            $messages[] = array(
                'msgId'  => $msgId,
                'msgBase64'  => base64_encode(self::serializeProtoBuf($data, $config[1]))
            );
        }

        return $messages;
    }

}