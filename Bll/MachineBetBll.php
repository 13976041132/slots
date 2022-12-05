<?php
/**
 * 机台Bet业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;

class MachineBetBll extends UserAdapterBll
{
    /**
     * 获取机台下注选项列表
     */
    public function getBetOptionList($uid, $machineId)
    {
        if ($this->checkCacheData($uid, self::$betOptions)) {
            if (self::$betOptions['machineId'] == $machineId) {
                return self::$betOptions['list'];
            }
        }

        $machineBet = Bll::machine()->getMachineBet($machineId);

        $betOptions = $machineBet['betOptions'];
        $betRaise = array_flip($machineBet['betRaise'] ?? array());
        $betPrompt = array_flip($machineBet['betPrompt'] ?? array());
        $betPopBubble = array_flip($machineBet['betPopBubble'] ?? array());

        // 处理过期 Bet 格式
        $betExpired = array();
        $betExpiredCfg = $machineBet['betExpired'] ?? array();
        foreach ($betExpiredCfg as $key => $val) {
            if (is_array($val)) {
                $betExpired += array_fill_keys($val, $key);
            } else {
                $betExpired[$val] = $key;
            }
        }

        // 处理 betUnlock：totalBet 与解锁等级对应关系
        $betUnlock = array();
        array_walk($machineBet['betUnlock'], function ($totalBet, $unlockLevel) use (&$betUnlock) {
            if (is_array($totalBet)) {
                $betUnlock += array_fill_keys($totalBet, $unlockLevel);
            } else {
                $betUnlock[$totalBet] = $unlockLevel;
            }
        });

        $unlockLevel = 1;
        $betOptionList = array();

        foreach ($betOptions as $_betMultiple => $_totalBet) {
            if (isset($betUnlock[$_totalBet])) {
                $unlockLevel = $betUnlock[$_totalBet];
            }
            $raiseLevel = isset($betRaise[$_totalBet]) ? $betRaise[$_totalBet] : 0;
            $promptLevel = isset($betPrompt[$_totalBet]) ? $betPrompt[$_totalBet] : 0;
            $expiredLevel = isset($betExpired[$_totalBet]) ? $betExpired[$_totalBet] : 0;
            $popBubbleLevel = isset($betPopBubble[$_totalBet]) ? $betPopBubble[$_totalBet] : 0;
            $betOptionList[] = array(
                'betMultiple' => (int)$_betMultiple,
                'totalBet' => (int)$_totalBet,
                'unlockLevel' => $unlockLevel,
                'raiseLevel' => $raiseLevel,
                'promptLevel' => $promptLevel,
                'expiredLevel' => $expiredLevel,
                'popBubbleLevel' => $popBubbleLevel
            );
        }

        self::$betOptions = array(
            'uid' => $uid, 'machineId' => $machineId, 'list' => $betOptionList
        );

        return $betOptionList;
    }

    /**
     * 获取已解锁的下注选项列表
     */
    public function getUnlockBetOptionList($uid, $machineId, $level = null)
    {
        $unlockedBetOptions = array();
        $betOptions = $this->getBetOptionList($uid, $machineId);
        $level = $level ?: $this->getUserInfo($uid, 'level');

        foreach ($betOptions as $option) {

            if ($option['unlockLevel'] > $level) {
                continue;
            }

            // expiredLevel 为 0 表示没有过期限制
            if ($option['expiredLevel'] != 0 && $option['expiredLevel'] <= $level && !defined('TEST_ID')) {
                continue;
            }

            $unlockedBetOptions[$option['totalBet']] = $option;
        }

        ksort($unlockedBetOptions);

        return array_values($unlockedBetOptions);
    }

    /**
     * 获取已解锁的下注额列表
     */
    public function getUnlockedBets($uid, $machineId, $level = null)
    {
        $unlockedBetOptions = $this->getUnlockBetOptionList($uid, $machineId, $level);

        return array_column($unlockedBetOptions, 'totalBet', 'betMultiple');
    }

    /**
     * 获取用户当前可下注的最大下注额
     */
    public function getMaxBet($uid, $machineId, $level = null)
    {
        $unlockedBets = $this->getUnlockedBets($uid, $machineId, $level);

        return array_pop($unlockedBets);
    }

    /**
     * 清除玩家在机台内的最高下注额
     */
    public function clearMaxBets($uid)
    {
        return Dao::redis()->del([Keys::maxBets($uid)]);
    }

    /**
     * 根据指定下注额匹配最接近的下注额
     */
    public function getClosestBet($uid, $unlockedBets, $totalBet, $offset = 0)
    {
        $unlockedBets = array_values($unlockedBets);
        $closestBet = $unlockedBets[0];
        $lowerBet = $upperBet = 0;

        foreach ($unlockedBets as $_totalBet) {
            if ($_totalBet == $totalBet) {
                return $totalBet;
            } elseif ($_totalBet < $totalBet) {
                $closestBet = $_totalBet;
                $lowerBet = $_totalBet;
            } else {
                $upperBet = $_totalBet;
                break;
            }
        }

        if ($lowerBet && $upperBet) {
            if ($upperBet - $totalBet < $totalBet - $lowerBet) {
                $closestBet = $upperBet;
            }
        }

        //根据档位偏移量，得到向上或向下N档的下注额
        if ($offset) {
            $index = array_search($closestBet, $unlockedBets);
            $index += $offset;
            $index = max(0, min($index, count($unlockedBets) - 1));
            $closestBet = $unlockedBets[$index];
        }

        return $closestBet;
    }

    /**
     * 根据推荐下注额匹配最接近的下注额(向下取)
     */
    public function getNearByBet($uid, $unlockedBets, $suggestBet)
    {
        $underBet = array_values($unlockedBets)[0];
        foreach ($unlockedBets as $totalBet) {
            if ($totalBet < $suggestBet) {
                $underBet = $totalBet;
            }
        }

        return $underBet;
    }

}