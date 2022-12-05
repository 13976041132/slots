<?php
/**
 * 用户适配器
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Library\Utils\Utils;

class UserAdapterBll
{
    protected static $abParameters = null;
    protected static $userInfo = null;
    protected static $rechargeInfo = null;
    protected static $analysisInfo = null;
    protected static $betOptions = null;

    public function clearCacheData()
    {
        self::$abParameters = null;
        self::$userInfo = null;
        self::$analysisInfo = null;
        self::$betOptions = null;
        self::$rechargeInfo = null;
    }

    public function checkCacheData($uid, $data)
    {
        if ($data === null || empty($data['uid']) || $data['uid'] != $uid) {
            return false;
        } else {
            return true;
        }
    }

    public function getRechargeInfo($uid, $field = '')
    {
        if (!$this->checkCacheData($uid, self::$rechargeInfo)) {
            self::$rechargeInfo = Bll::recharge()->getRechargeInfo($uid);
        }

        if (!$field) {
            return self::$rechargeInfo;
        }

        return self::$rechargeInfo[$field];
    }
    
    public function getUserInfo($uid, $field = '')
    {
        if (!$this->checkCacheData($uid, self::$userInfo)) {
            self::$userInfo = Bll::user()->getOne($uid);
        }

        if (!$field) {
            return self::$userInfo;
        }

        return self::$userInfo[$field];
    }

    public function getAnalysisInfo($uid, $field = '')
    {
        if (!$this->checkCacheData($uid, self::$analysisInfo)) {
            self::$analysisInfo = Bll::analysis()->getAnalysisInfo($uid);
        }

        if (!$field) {
            return self::$analysisInfo;
        }

        return self::$analysisInfo[$field];
    }

    /**
     * 判断用户属性是否与指定条件匹配
     * 同时比对多项属性，有任意一项不匹配，则认为整体不匹配
     */
    public function isUserMatched($uid, $conditions)
    {
        if (!$conditions || !is_array($conditions)) {
            return true;
        }

        //检查资产余额
        if (!empty($conditions['balance'])) {
            $balance = $this->getUserInfo($uid, 'coinBalance');
            if (!Utils::isValueMatched($balance, $conditions['balance'])) {
                return false;
            }
        }

        //匹配注册天数
        if (isset($conditions['loginDays'])) {
            $conditions['registerDay'] = $conditions['loginDays'];
        }
        if (!empty($conditions['registerDay'])) {
            $regTime = $this->getUserInfo($uid, 'create_time');
            /**
             * @var string $regTime
             */
            $regDate = date('Y-m-d', $regTime);
            $registerDays = (strtotime(today()) - strtotime($regDate)) / 86400 + 1;
            if (!Utils::isValueMatched($registerDays, $conditions['registerDay'])) {
                return false;
            }
        }

        return true;
    }

}