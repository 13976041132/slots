<?php
/**
 * 缓存数据Key生成器
 */

namespace FF\Factory;

use FF\Framework\Utils\Config;

class Keys
{
    //构建key
    public static function buildKey(...$args)
    {
        $prefix = Config::get('core', 'cache_key_prefix');
        $keys = [$prefix];
        foreach ($args as $val) {
            $keys[] = $val;
        }
        $key = implode(':', $keys);
        return $key;
    }

    /**
     * session信息
     */
    public static function session($sessionId)
    {
        return self::buildKey('Session', $sessionId);
    }

    /**
     * sessionId
     */
    public static function sessionId($uid)
    {
        return self::buildKey('SessionId', $uid);
    }

    /**
     * 配置版本记录
     */
    public static function configVersion()
    {
        return self::buildKey('ConfigVersion', APP_ID);
    }

    /**
     * 配置数据记录
     */
    public static function configData($name)
    {
        return self::buildKey('ConfigData', APP_ID, $name);
    }

    /**
     * 用户信息
     */
    public static function userInfo($uid)
    {
        return self::buildKey('UserInfo', $uid);
    }

    /**
     * 游戏信息
     */
    public static function gameInfo($uid, $machineId)
    {
        return self::buildKey('GameInfo', $uid, $machineId);
    }

    /**
     * 分析信息
     */
    public static function analysisInfo($uid)
    {
        return self::buildKey('AnalysisInfo', $uid);
    }

    /**
     * jackpot创建时间
     */
    public static function jackpotCreateTime()
    {
        return self::buildKey("JackpotCreateTime");
    }

    /**
     * 用户AB测试参数信息
     */
    public static function abParams($uid)
    {
        return self::buildKey('AbParams', $uid);
    }

    /**
     * 玩家干预信息
     */
    public static function interveneInfo($uid, $type)
    {
        return self::buildKey('InterveneInfo', $uid, $type);
    }

    /**
     * 每日干预次数
     */
    public static function dailyInterveneTimes($uid)
    {
        return self::buildKey('DailyInterveneTimes', $uid, today());
    }

    /**
     * 最高下注额
     */
    public static function maxBets($uid)
    {
        return self::buildKey('MaxBets', $uid);
    }

    /**
     * 公共奖池 PublicJackpot
     */
    public static function publicJackpot($machineId)
    {
        return self::buildKey('PublicJackpot', $machineId);
    }

    /**
     * 机台测试详细信息
     */
    public static function slotsTestInfo($testId)
    {
        return self::buildKey("SlotsTestInfo", $testId);
    }

    /**
     * 机台测试计划列表
     */
    public static function slotsTestPlans()
    {
        return self::buildKey("SlotsTestPlans");
    }

    /**
     * 机台测试统计结果
     */
    public static function slotsTestResult($testId, $machineId)
    {
        return self::buildKey("SlotsTestResult", $testId, $machineId);
    }

    /**
     * 机台测试用户数据
     */
    public static function slotsTestUserData($testId)
    {
        return self::buildKey("SlotsTestUserData", $testId);
    }

    public static function slotsTestUserInfo($testId, $uid)
    {
        return self::buildKey("SlotsTestUserInfo", $testId, $uid);
    }

    public static function slotsTestLock($testId)
    {
        return self::buildKey("SlotsTestLock", $testId);
    }

    public static function buffInfo($uid)
    {
        return self::buildKey("BuffInfo", $uid);
    }
}