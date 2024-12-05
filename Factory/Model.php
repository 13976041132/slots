<?php
/**
 * 模型对象工厂
 */

namespace FF\Factory;

use FF\App\GameMain\Model\Main\AccountModel;
use FF\App\GameMain\Model\Main\ChatLogModel;
use FF\App\GameMain\Model\Main\FriendsModel;
use FF\App\GameMain\Model\Main\FriendsRequestsModel;
use FF\App\GameMain\Model\Main\UserBllRewardDataModel;
use FF\App\GameMain\Model\Main\UserInviteDataModel;
use FF\App\GameMain\Model\Main\UserModel;
use FF\App\GameMain\Model\Main\SuggestUsersModel;
use FF\App\GameMain\Model\Main\UserDailyFirstLoginLogModel;
use FF\Framework\Mode\Factory;

class Model extends Factory
{
    /**
     * @return UserModel
     */
    public static function user()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserModel');
    }

    /**
     * @return AccountModel
     */
    public static function account()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\AccountModel');
    }

    /**
     * @return FriendsModel
     */
    public static function friends()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\FriendsModel');
    }

    /**
     * @return FriendsRequestsModel
     */
    public static function friendsRequests()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\FriendsRequestsModel');
    }
    /**
     * @return ChatLogModel
     */
    public static function chatLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ChatLogModel');
    }

    /**
     * @return UserInviteDataModel
     */
    public static function userInviteData()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserInviteDataModel');
    }

    /**
     * @return UserBllRewardDataModel
     */
    public static function userBllRewardData()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserBllRewardDataModel');
    }

    /**
     * @return SuggestUsersModel
     */
    public static function suggestUser()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\SuggestUsersModel');
    }

    /**
     * @return UserDailyFirstLoginLogModel
     */
    public static function userDailyFirstLoginLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserDailyFirstLoginLogModel');
    }

}