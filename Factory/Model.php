<?php
/**
 * 模型对象工厂
 */

namespace FF\Factory;

use FF\App\GameMain\Model\Main\ChatLogModel;
use FF\App\GameMain\Model\Main\ClubChatLogModel;
use FF\App\GameMain\Model\Main\ClubsModel;
use FF\App\GameMain\Model\Main\ClubUsersModel;
use FF\App\GameMain\Model\Main\FriendsModel;
use FF\App\GameMain\Model\Main\FriendsRequestsModel;
use FF\App\GameMain\Model\Main\SuggestUsersModel;
use FF\App\GameMain\Model\Main\UserBllRewardDataModel;
use FF\App\GameMain\Model\Main\UserDailyFirstLoginLogModel;
use FF\App\GameMain\Model\Main\UserInviteDataModel;
use FF\App\GameMain\Model\Main\UserModel;
use FF\App\GameMain\Model\Main\UserRequestLastModel;
use FF\App\GameMain\Model\Main\UserClubRequestLogModel;
use FF\App\GameMain\Model\Main\ClubRankLogModel;
use FF\App\GameMain\Model\Main\ClubRewardsModel;
use FF\App\GameMain\Model\Main\ClubJackpotLogModel;
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

    /**
     * @return UserRequestLastModel
     */
    public static function userRequestLast()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserRequestLastModel');
    }

    /**
     * @return ClubsModel
     */
    public static function clubs()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubsModel');
    }
    /**
     * @return ClubChatLogModel
     */
    public static function clubChatLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubChatLogModel');
    }
    /**
     * @return ClubUsersModel
     */
    public static function clubUsers()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubUsersModel');
    }

    /**
     * @return UserClubRequestLogModel
     */
    public static function userClubRequestLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserClubRequestLogModel');
    }

    /**
     * @return ClubRankLogModel
     */
    public static function clubRankLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubRankLogModel');
    }

    /**
     * @return ClubRewardsModel
     */
    public static function clubRewards()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubRewardsModel');
    }

    /**
     * @return ClubJackpotLogModel
     */
    public static function clubJackpotLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ClubJackpotLogModel');
    }
}