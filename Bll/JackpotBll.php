<?php
/**
 * Jackpot业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class JackpotBll
{
    /**
     * 获取jackpot信息
     */
    public function getJackpots($machineId)
    {
        $jackpots = Config::get('machine/jackpots', $machineId);
        if (!$jackpots) return array();

        $jackpotNames = array_column($jackpots, 'jackpotName');
        $jackpotNames = array_values(array_unique($jackpotNames));
        $jackpotKeys = array();
        foreach ($jackpotNames as $jackpotName) {
            $jackpotKeys[] = "{$machineId}-{$jackpotName}";
        }

        $cacheKey = Keys::jackpotCreateTime();
        $createTimes = Dao::redis()->hMGet($cacheKey, $jackpotKeys);

        $time = time();
        $createTimeUpdates = array();

        foreach ($jackpots as &$jackpot) {
            $jackpotName = $jackpot['jackpotName'];
            $jackpot['minBetLevel'] = !empty($jackpot['activeLevel']) ? $jackpot['activeLevel'][0] : 0;
            $jackpot['maxBetLevel'] = !empty($jackpot['activeLevel']) ? $jackpot['activeLevel'][1] : 0;
            $jackpot['awardByBet'] = $jackpot['awardByBet'] ? true : false;
            $jackpotKey = "{$machineId}-{$jackpotName}";
            if (empty($createTimes[$jackpotKey])) {
                $createTimeUpdates[$jackpotKey] = $time;
                $createTime = $time;
            } else {
                $createTime = (int)$createTimes[$jackpotKey];
            }
            $jackpot['createTime'] = $createTime;
        }

        if ($createTimeUpdates) {
            Dao::redis()->hMset($cacheKey, $createTimeUpdates);
        }

        return $jackpots;
    }

    /**
     * 重置jackpot创建时间
     */
    public function resetCreateTime($machineId, $jackpotName, $relatedMachineIds = array())
    {
        $machineIds = [$machineId];

        if ($relatedMachineIds) {
            $machineIds = array_merge($machineIds, $relatedMachineIds);
        }

        $time = time();

        $createTimes = array();
        foreach ($machineIds as $machineId) {
            $createTimes["{$machineId}-{$jackpotName}"] = $time;
        }

        $key = Keys::jackpotCreateTime();
        Dao::redis()->hMset($key, $createTimes);
    }


    /**
     * 计算jackpot奖池
     * 通过 totalBet 计算 jackpot 区间
     */
    public function getJackpotPot($jackpot, $totalBet = 0)
    {
        if ($jackpot['awardByBet']) {
            $awardBegin = bcmul($jackpot['awardBegin'], $totalBet, 2);
            $awardEnd = bcmul($jackpot['awardEnd'], $totalBet, 2);
        } else {
            $awardBegin = (string)$jackpot['awardBegin'];
            $awardEnd = (string)$jackpot['awardEnd'];
        }

        //奖池大小固定不变
        if ($awardBegin === $awardEnd) {
            return $awardBegin;
        }

        if (empty($jackpot['createTime'])) {
            FF::throwException(Code::SYSTEM_ERROR);
        }

        $timePass = max(0, time() - $jackpot['createTime']);

        if ($awardEnd) {
            $timePass = $timePass % $jackpot['duration'] + 1;
            $increment = bcdiv(($awardEnd - $awardBegin) * $timePass, $jackpot['duration']);
            $pot = bcadd($awardBegin, $increment, 2);
        } else {
            //无限增长模式
            $growth = $jackpot['growthMultiple'] * ($timePass / $jackpot['duration']);
            $pot = bcmul($awardBegin, $growth, 2);
        }

        return (float)$pot;
    }

    /**
     * 下注更新机台公共Jackpot
     */
    public function updatePublicJackpot($machineId, $jackpotId, $addition)
    {
        $jackpotKey = Keys::publicJackpot($machineId);
        Dao::redis()->hIncrByFloat($jackpotKey, $jackpotId, $addition);
    }
}