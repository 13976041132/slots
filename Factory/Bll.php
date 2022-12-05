<?php
/**
 * 业务逻辑层对象工厂
 */

namespace FF\Factory;

use FF\Bll\AdminBll;
use FF\Bll\AnalysisBll;
use FF\Bll\AsyncTaskBll;
use FF\Bll\BetLogBll;
use FF\Bll\ConfigBll;
use FF\Bll\FileSourceBll;
use FF\Bll\FreespinBll;
use FF\Bll\GameBll;
use FF\Bll\Intervene\BankruptEventBll;
use FF\Bll\Intervene\ExperienceEventBll;
use FF\Bll\Intervene\ExtremeEventBll;
use FF\Bll\Intervene\HighBetBll;
use FF\Bll\Intervene\LowBetBll;
use FF\Bll\Intervene\NoviceBll;
use FF\Bll\Intervene\NoviceEventBll;
use FF\Bll\Intervene\RechargeEventBll;
use FF\Bll\Intervene\TooRichBll;
use FF\Bll\ItemBll;
use FF\Bll\JackpotBll;
use FF\Bll\LogBll;
use FF\Bll\MachineBetBll;
use FF\Bll\MachineBll;
use FF\Bll\MachineCollectBll;
use FF\Bll\MachineCollectGameBll;
use FF\Bll\OnlineBll;
use FF\Bll\PermBll;
use FF\Bll\RechargeBll;
use FF\Bll\SessionBll;
use FF\Bll\SlotsTestBll;
use FF\Bll\UltraBetBll;
use FF\Bll\UserAdapterBll;
use FF\Bll\UserBll;
use FF\Bll\UserSessionBll;
use FF\Bll\VersionBll;
use FF\Bll\WheelBll;
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
     * @return OnlineBll
     */
    public static function online()
    {
        return self::getInstance('FF\Bll\OnlineBll');
    }


    /**
     * @return PermBll
     */
    public static function perm()
    {
        return self::getInstance('FF\Bll\PermBll');
    }


    /**
     * @return VersionBll
     */
    public static function version()
    {
        return self::getInstance('FF\Bll\VersionBll');
    }

    /**
     * @return AdminBll
     */
    public static function admin()
    {
        return self::getInstance('FF\Bll\AdminBll');
    }

    /**
     * @return UserBll
     */
    public static function user()
    {
        return self::getInstance('FF\Bll\UserBll');
    }

    /**
     * @return UserSessionBll
     */
    public static function userSession()
    {
        return self::getInstance('FF\Bll\UserSessionBll');
    }

    /**
     * @return UserAdapterBll
     */
    public static function userAdapter()
    {
        return self::getInstance('FF\Bll\UserAdapterBll');
    }

    /**
     * @return UltraBetBll
     */
    public static function ultraBet()
    {
        return self::getInstance('FF\Bll\UltraBetBll');
    }

    /**
     * @return ItemBll
     */
    public static function item()
    {
        return self::getInstance('FF\Bll\ItemBll');
    }

    /**
     * @return GameBll
     */
    public static function game()
    {
        return self::getInstance('FF\Bll\GameBll');
    }

    /**
     * @return AnalysisBll
     */
    public static function analysis()
    {
        return self::getInstance('FF\Bll\AnalysisBll');
    }

    /**
     * @return WheelBll
     */
    public static function wheel()
    {
        return self::getInstance('FF\Bll\WheelBll');
    }

    /**
     * @return MachineBll
     */
    public static function machine()
    {
        return self::getInstance('FF\Bll\MachineBll');
    }

    /**
     * @return MachineBetBll
     */
    public static function machineBet()
    {
        return self::getInstance('FF\Bll\MachineBetBll');
    }

    /**
     * @return MachineCollectBll
     */
    public static function machineCollect()
    {
        return self::getInstance('FF\Bll\MachineCollectBll');
    }

    /**
     * @return SlotsTestBll
     */
    public static function slotsTest()
    {
        return self::getInstance('FF\Bll\SlotsTestBll');
    }

    /**
     * @return FreespinBll
     */
    public static function freespin()
    {
        return self::getInstance('FF\Bll\FreespinBll');
    }

    /**
     * @return JackpotBll
     */
    public static function jackpot()
    {
        return self::getInstance('FF\Bll\JackpotBll');
    }

    /**
     * @return ConfigBll
     */
    public static function config()
    {
        return self::getInstance('FF\Bll\ConfigBll');
    }

    /**
     * @return AsyncTaskBll
     */
    public static function asyncTask()
    {
        return self::getInstance('FF\Bll\AsyncTaskBll');
    }

    /**
     * @return LogBll
     */
    public static function log()
    {
        return self::getInstance('FF\Bll\LogBll');
    }

    /**
     * @return BetLogBll
     */
    public static function betLog()
    {
        return self::getInstance('FF\Bll\BetLogBll');
    }

    /**
     * @return NoviceBll
     */
    public static function ivNovice()
    {
        return self::getInstance('FF\Bll\Intervene\NoviceBll');
    }

    /**
     * @return TooRichBll
     */
    public static function ivTooRich()
    {
        return self::getInstance('FF\Bll\Intervene\TooRichBll');
    }

    /**
     * @return HighBetBll
     */
    public static function ivHighBet()
    {
        return self::getInstance('FF\Bll\Intervene\HighBetBll');
    }

    /**
     * @return LowBetBll
     */
    public static function ivLowBet()
    {
        return self::getInstance('FF\Bll\Intervene\LowBetBll');
    }

    /**
     * @return RechargeEventBll
     */
    public static function ivRechargeEvent()
    {
        return self::getInstance('FF\Bll\Intervene\RechargeEventBll');
    }

    /**
     * @return NoviceEventBll
     */
    public static function ivNoviceEvent()
    {
        return self::getInstance('FF\Bll\Intervene\NoviceEventBll');
    }

    /**
     * @return ExperienceEventBll
     */
    public static function ivExperienceEvent()
    {
        return self::getInstance('FF\Bll\Intervene\ExperienceEventBll');
    }

    /**
     * @return ExtremeEventBll
     */
    public static function ivExtremeEvent()
    {
        return self::getInstance('FF\Bll\Intervene\ExtremeEventBll');
    }

    /**
     * @return BankruptEventBll
     */
    public static function ivBankruptEvent()
    {
        return self::getInstance('FF\Bll\Intervene\BankruptEventBll');
    }

    /**
     * @return FileSourceBll
     */
    public static function fileSource()
    {
        return self::getInstance('FF\Bll\FileSourceBll');
    }

    /**
     * @return MachineCollectGameBll
     */
    public static function machineCollectGame()
    {
        return self::getInstance('FF\Bll\MachineCollectGameBll');
    }

    /**
     * @return RechargeBll
     */
    public static function recharge()
    {
        return self::getInstance('FF\Bll\RechargeBll');
    }

}