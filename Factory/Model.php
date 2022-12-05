<?php
/**
 * 模型对象工厂
 */

namespace FF\Factory;

use FF\App\Admin\Model\AdminModel;
use FF\App\Admin\Model\OperationLogModel;
use FF\App\Admin\Model\OperationTargetModel;
use FF\App\Admin\Model\PermGroupModel;
use FF\App\Admin\Model\PermItemModel;
use FF\App\Admin\Model\PermRoleBindModel;
use FF\App\Admin\Model\PermRoleItemModel;
use FF\App\Admin\Model\PermRoleModel;
use FF\App\Admin\Model\SlotsTestModel;
use FF\App\Admin\Model\VersionBuildModel;
use FF\App\GameMain\Model\Analysis\UserBetDataModel;
use FF\App\GameMain\Model\Config\FeatureGameModel;
use FF\App\GameMain\Model\Config\MachineItemModel;
use FF\App\GameMain\Model\Config\MachineModel;
use FF\App\GameMain\Model\Config\PayLineModel;
use FF\App\GameMain\Model\Config\PayTableModel;
use FF\App\GameMain\Model\Config\SampleItemsModel;
use FF\App\GameMain\Model\Config\SampleModel;
use FF\App\GameMain\Model\Config\SampleRefModel;
use FF\App\GameMain\Model\Config\VersionModel;
use FF\App\GameMain\Model\Log\BetLogModel;
use FF\App\GameMain\Model\Log\DataLogModel;
use FF\App\GameMain\Model\Log\EventLogModel;
use FF\App\GameMain\Model\Main\AccountModel;
use FF\App\GameMain\Model\Main\AnalysisModel;
use FF\App\GameMain\Model\Main\FreeSpinModel;
use FF\App\GameMain\Model\Main\GameInfoModel;
use FF\App\GameMain\Model\Main\ItemModel;
use FF\App\GameMain\Model\Main\OnlineModel;
use FF\App\GameMain\Model\Main\UserModel;
use FF\Framework\Mode\Factory;

class Model extends Factory
{
    /**
     * @return AdminModel
     */
    public static function admin()
    {
        return self::getInstance('FF\App\Admin\Model\AdminModel');
    }

    /**
     * @return PermGroupModel
     */
    public static function permGroup()
    {
        return self::getInstance('FF\App\Admin\Model\PermGroupModel');
    }

    /**
     * @return PermRoleModel
     */
    public static function permRole()
    {
        return self::getInstance('FF\App\Admin\Model\PermRoleModel');
    }

    /**
     * @return PermItemModel
     */
    public static function permItem()
    {
        return self::getInstance('FF\App\Admin\Model\PermItemModel');
    }

    /**
     * @return PermRoleItemModel
     */
    public static function permRoleItem()
    {
        return self::getInstance('FF\App\Admin\Model\PermRoleItemModel');
    }

    /**
     * @return PermRoleBindModel
     */
    public static function permRoleBind()
    {
        return self::getInstance('FF\App\Admin\Model\PermRoleBindModel');
    }

    /**
     * @return SlotsTestModel
     */
    public static function slotsTest()
    {
        return self::getInstance('FF\App\Admin\Model\SlotsTestModel');
    }

    /**
     * @return UserBetDataModel
     */
    public static function userBetData()
    {
        return self::getInstance('FF\App\GameMain\Model\Analysis\UserBetDataModel');
    }

    /**
     * @return UserModel
     */
    public static function user()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\UserModel');
    }

    /**
     * @return AnalysisModel
     */
    public static function analysis()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\AnalysisModel');
    }

    /**
     * @return FreeSpinModel
     */
    public static function freespin()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\FreeSpinModel');
    }

    /**
     * @return GameInfoModel
     */
    public static function gameInfo()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\GameInfoModel');
    }

    /**
     * @return ItemModel
     */
    public static function item()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\ItemModel');
    }

    /**
     * @return BetLogModel
     */
    public static function betLog($tblSubfix = '')
    {
        if (defined('TEST_ID')) {
            $testId = TEST_ID;
        } else {
            $testId = is_numeric($tblSubfix) ? $tblSubfix : substr($tblSubfix, 4);
        }

        return self::getInstance('FF\App\GameMain\Model\Log\BetLogModel', $testId, [$testId]);
    }

    /**
     * @return EventLogModel
     */
    public static function eventLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Log\EventLogModel');
    }

    /**
     * @return MachineModel
     */
    public static function machine()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\MachineModel');
    }

    /**
     * @return MachineItemModel
     */
    public static function machineItem()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\MachineItemModel');
    }

    /**
     * @return PayLineModel
     */
    public static function payline()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\PayLineModel');
    }

    /**
     * @return PayTableModel
     */
    public static function paytable()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\PayTableModel');
    }

    /**
     * @return FeatureGameModel
     */
    public static function featureGame()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\FeatureGameModel');
    }

    /**
     * @return SampleModel
     */
    public static function sample()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\SampleModel');
    }

    /**
     * @return SampleItemsModel
     */
    public static function sampleItems()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\SampleItemsModel');
    }

    /**
     * @return SampleRefModel
     */
    public static function sampleRef()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\SampleRefModel');
    }

    /**
     * @return VersionModel
     */
    public static function version()
    {
        return self::getInstance('FF\App\GameMain\Model\Config\VersionModel');
    }

    /**
     * @return OperationLogModel
     */
    public static function operationLog()
    {
        return self::getInstance('FF\App\Admin\Model\OperationLogModel');
    }

    /**
     * @return OperationTargetModel
     */
    public static function operationTarget()
    {
        return self::getInstance('FF\App\Admin\Model\OperationTargetModel');
    }

    /**
     * @return AccountModel
     */
    public static function account()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\AccountModel');
    }

    /**
     * @return VersionBuildModel
     */
    public static function versionBuild()
    {
        return self::getInstance('FF\App\Admin\Model\VersionBuildModel');
    }

    /**
     * @return OnlineModel
     */
    public static function online()
    {
        return self::getInstance('FF\App\GameMain\Model\Main\OnlineModel');
    }

    /**
     * @return DataLogModel
     */
    public static function dataLog()
    {
        return self::getInstance('FF\App\GameMain\Model\Log\DataLogModel');
    }
}