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
use FF\Bll\MessageNotifyBll;
use FF\Bll\FriendCacheBll;
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
}