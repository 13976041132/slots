<?php

namespace FF\Middleware;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;
use FF\Constants\Exceptions;

class CheckSignature
{
    public function handle($next)
    {
        $this->checkSignature();
        return $next();
    }

    public function checkSignature()
    {
        $param = json_decode(Input::request('k'), true);
        $sign = $param['sign'] ?? '';
        if(isset($param['sign'])) {
            unset($param['sign']);
        }
        $uid = Bll::session()->get('uid');
        $param['uid'] = $uid;

        ksort($param);
        $param['secretKey'] = Bll::userRequestLast($param['uid'])->get('secretKey');
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
        if (md5($str) != $sign && md5($strBak) != $sign) {
            Log::error("sign check fail, client_secret:{$sign}, str1:{$str}, str2:{$strBak}");
            FF::throwException(Exceptions::FAILED_SIGN);
        }
        Bll::userRequestLast($uid)->setFreshSecretKey(true);
        return true;
    }
}