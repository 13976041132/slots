<?php

namespace FF\Bll;

use FF\Constants\Exceptions;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;

class UserRequestLastBll
{
    private $info = [];

    private $secretFresh = false;

    public function __construct()
    {
        $this->info = Model::userRequestLast()->getOneById(Bll::session()->get('uid'));
    }

    //info
    public function getInfo($uid)
    {
        return $this->info;
    }

    public function touchSecretKey($secretFresh = false)
    {
        if (empty($this->info['secretKey']) || $secretFresh) {
            $this->info['secretKey'] = md5(createNonceStr(32));
        }

        return $this->info['secretKey'];
    }

    public function getRequestId()
    {
        return $this->info['requestId'] ?? 0;
    }
    public function save($uid, $params, $response)
    {
        $log = [
            'uid' => $uid,
            'request' => $params ?? '{}',
            'messageId' => (int)Input::request('c'),
            'requestId' => (string)Input::request('q'),
            'response' => json_encode($response),
            'requestTime' => time(),
            'secretKey' => $this->touchSecretKey($this->secretFresh),
        ];
        if (Model::userRequestLast()->insert($log, true)) {
            $this->info = $log;
            $this->secretFresh = false;
        }
    }
    public function checkSignature()
    {
        $headers = getallheaders();
        $secret = $headers['secret'] ?? '';
        $param = json_decode(Input::request('k'), true);
        $param['uid'] = Bll::session()->get('uid');
        ksort($param);
        $param['secretKey'] = $this->info['secretKey'];
        $paramBak = $param;
        // 生成签名字符串
        array_walk($param, function (&$value, $key) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $value = "{$key}={$value}";
        });
        array_walk($paramBak, function (&$value, $key) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $value = trim($value);
            $value = "{$key}={$value}";
        });
        $str = implode('&', $param);
        $strBak = implode('&', $paramBak);
        // 比较签名
        if (md5($str) != $secret && md5($strBak) != $secret) {
            Log::error("sign check fail, client_secret:{$secret}, str1:{$str}, str2:{$strBak}");
            FF::throwException(Exceptions::FAILED_SIGN);
        }

        $this->secretFresh = true;
        return true;
    }
}