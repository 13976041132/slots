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
     * 用户信息
     */
    public static function userInfo($uid)
    {
        return self::buildKey('UserInfo', $uid);
    }

    /**
     * 朋友信息
     */
    public static function friendInfo($uid)
    {
        return self::buildKey('FriendInfo', $uid);
    }

    /**
     * Friends
     */
    public static function friends($uid)
    {
        return self::buildKey('Friends', $uid);
    }

    /**
     * limitAddFriends
     */
    public static function limitAddfriends($uid)
    {
        return self::buildKey('limitAddFriends', $uid);
    }

    /**
     * RequestFriends
     */
    public static function requestFriends($uid)
    {
        return self::buildKey('RequestFriends', $uid);
    }

    /**
     * 申请好友次数
     */
    public static function requestFriendTimes($uid)
    {
        return self::buildKey('RequestFriendTimes', $uid);
    }

    /**
     * 发送好友免费金币次数
     */
    public static function sentFriendCoins($uid, $type)
    {
        return self::buildKey('SentFriendCoins', $uid, $type);
    }
    public static function sentFriendCoinsLock($uid, $fuid)
    {
        return self::buildKey('SentFriendCoinsLock', $uid, $fuid);
    }
    public static function awardFriendCoinsLock($uid)
    {
        return self::buildKey('AwardFriendCoinsLock', $uid);
    }

    /**
     * 发送好友免费邮票次数
     */
    public static function sentFriendStamp($uid, $type)
    {
        return self::buildKey('SentFriendStamp', $uid, $type);
    }

    public static function sentFriendStampLock($uid, $fuid)
    {
        return self::buildKey('SentFriendStampLock', $uid, $fuid);
    }
    public static function awardFriendStampLock($uid)
    {
        return self::buildKey('AwardFriendStampLock', $uid);
    }
    public static function bllMessageQueue($uid)
    {
        return self::buildKey('BllMessageQueue', $uid);
    }

    public static function lastChatTime($uuid)
    {
        return self::buildKey('LastChatTime', $uuid);
    }

    public static function suggestFriendSet()
    {
        return self::buildKey('SuggestFriendSet');
    }
}