<?php
/**
 * 干预业务逻辑基类
 */

namespace FF\Bll\Intervene;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Utils\Config;

class InterveneBll
{
    protected $type = '';

    public function getCacheKey($uid)
    {
        return Keys::interveneInfo($uid, $this->type);
    }

    public function getBaseInterveneCfg($key = null, $type = null)
    {
        $cfgKey = $type ?: $this->type;
        if ($key) {
            $cfgKey .= '/' . $key;
        }

        return Config::get('machine/intervene', $cfgKey, false);
    }

    public function getTrigCondition()
    {
        return $this->getBaseInterveneCfg('trigCondition');
    }

    public function getEndCondition()
    {
        return $this->getBaseInterveneCfg('endCondition');
    }

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 设定返回格式
        return array(
            'isIntervene' => false,     // 是否进行干预
            'isExit' => false,          // 是否退出
            'interveneType' => '',      // 干预类型
            'interveneValue' => '',     // 干预值
            'interveneFlag' => ''       // 干预标识符
        );
    }

    public function checkInterveneExit($uid, $interveneInfo, $userInfo)
    {
        return false;
    }

    public function onInterveneTrigger($uid, $reason)
    {
        Bll::log()->addEventLog($uid, 'intervene_trigger', $this->type, $reason);
    }

    public function onInterveneExit($uid, $reason)
    {
        Bll::log()->addEventLog($uid, 'intervene_exit', $this->type, $reason);
    }

    public function initInterveneInfo($uid, $data)
    {
        $cacheKey = $this->getCacheKey($uid);

        Dao::redis()->del($cacheKey);
        Dao::redis()->hMSet($cacheKey, $data);
        Dao::redis()->expire($cacheKey, 15 * 86400);
    }

    public function getInterveneInfo($uid)
    {
        $cacheKey = $this->getCacheKey($uid);

        return Dao::redis()->hGetAll($cacheKey);
    }

    public static function getInterveneInfoByType($uid, $type)
    {
        $cacheKey = Keys::interveneInfo($uid, $type);

        return Dao::redis()->hGetAll($cacheKey);
    }

    public function updateInterveneInfo($uid, $data)
    {
        $cacheKey = $this->getCacheKey($uid);

        Dao::redis()->hMSet($cacheKey, $data);
    }

    public function incInterveneInfoByKey($uid, $key, $num)
    {
        $cacheKey = $this->getCacheKey($uid);

        return Dao::redis()->hIncrByFloat($cacheKey, $key, $num);
    }

    public function clearInterveneInfo($uid)
    {
        $cacheKey = $this->getCacheKey($uid);

        Dao::redis()->del($cacheKey);
    }

    /**
     * 获取今日干预次数
     */
    public function getDailyInterveneTimes($uid, $reason = '')
    {
        $key = $this->type;
        if ($reason) $key = $this->type . '-' . $reason;

        $cacheKey = Keys::dailyInterveneTimes($uid);

        return (int)Dao::redis()->hGet($cacheKey, $key);
    }

    /**
     * 累加今日干预次数
     */
    public function addDailyInterveneTimes($uid, $reason = '')
    {
        $key = $this->type;
        if ($reason) $key = $this->type . '-' . $reason;

        $cacheKey = Keys::dailyInterveneTimes($uid);

        $times = Dao::redis()->hIncrBy($cacheKey, $key, 1);
        if ($times == 1) {
            Dao::redis()->expire($cacheKey, 86400);
        }

        return $times;
    }
}