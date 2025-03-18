<?php
/**
 * 业务逻辑层对象工厂
 */

namespace FF\Factory;

use FF\Bll\ChatLogBll;
use FF\Bll\FriendsBll;
use FF\Bll\LoginUserBll;
use FF\Bll\SessionBll;
use FF\Bll\UserBll;
use FF\Bll\ClubBll;
use FF\Bll\ClubUserBll;
use FF\Bll\RankBll;
use FF\Bll\MessageNotifyBll;
use FF\Bll\FriendCacheBll;
use FF\Bll\ClubCacheBll;
use FF\Bll\UserRequestLastBll;
use FF\Bll\ConfigBll;
use FF\Bll\ClubOptionBll;
use FF\Framework\Mode\Factory;

class Bll extends Factory
{
    /**
     * @return SessionBll
     */
    public static function session()
    {
        return self::getInstance('FF\Bll\SessionBll');
    }

    /**
     * @return ConfigBll
     */
    public static function config()
    {
        return self::getInstance('FF\Bll\ConfigBll');
    }

    /**
     * @return FriendsBll
     */
    public static function friends()
    {
        return self::getInstance('FF\Bll\FriendsBll');
    }

    /**
     * @return FriendCacheBll
     */
    public static function friendCache()
    {
        return self::getInstance('FF\Bll\FriendCacheBll');
    }

    /**
     * @return UserBll
     */
    public static function user()
    {
        return self::getInstance('FF\Bll\UserBll');
    }

    /**
     * @return MessageNotifyBll
     */
    public static function messageNotify()
    {
        return self::getInstance('FF\Bll\MessageNotifyBll');
    }

    /**
     * @return ChatLogBll
     */
    public static function chatLog()
    {
        return self::getInstance('FF\Bll\ChatLogBll');
    }

    /**
     * @return LoginUserBll
     */
    public static function loginUser()
    {
        return self::getInstance('FF\Bll\LoginUserBll');
    }

    /**
     * @return ClubBll
     */
    public static function club()
    {
        return self::getInstance('FF\Bll\ClubBll');
    }

    /**
     * @return ClubCacheBll
     */
    public static function clubCache()
    {
        return self::getInstance('FF\Bll\ClubCacheBll');
    }

    /**
     * @return ClubUserBll
     */
    public static function clubUser()
    {
        return self::getInstance('FF\Bll\ClubUserBll');
    }

    /**
     * @return RankBll
     */
    public static function rank()
    {
        return self::getInstance('FF\Bll\RankBll');
    }

    /**
     * @return UserRequestLastBll
     */
    public static function userRequestLast($uid)
    {
        return self::getInstance('FF\Bll\UserRequestLastBll', $uid);
    }

    /**
     * @return ClubOptionBll
     */
    public static function clubOption()
    {
        return self::getInstance('FF\Bll\ClubOptionBll');
    }
}