<?php

namespace FF\Middleware;

use FF\Factory\Bll;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;

class CheckSignature
{
    public function handle($next)
    {
        $this->checkSignature();
        return $next();
    }

    public function checkSignature()
    {
        $secret = $_SERVER['HTTP_SECRET'] ?? '';
        $param = json_decode(Input::request('k'), true);
        $param['uid'] = Bll::session()->get('uid');
        ksort($param);
        $param['secretKey'] = Bll::userRequestLast()->get('secretKey');
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
            //FF::throwException(Exceptions::FAILED_SIGN);
        }
        Bll::userRequestLast()->setFreshSecretKey(true);
        return true;
    }
}