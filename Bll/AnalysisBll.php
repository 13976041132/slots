<?php
/**
 * 游戏数据分析业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Dao;

class AnalysisBll
{
    public function getCacheKey($uid)
    {
        return 'UserGameInfo:' . $uid;
    }

    public function getHashKey($machineId = null)
    {
        return $machineId ? "gameInfo{$machineId}" : 'gameInfo';
    }

    public function initAnalysisInfo()
    {
        return array(
            'SpinInfo' => ['isNeedRefresh' => 1, 'totalSpinTimes' => 0],
            'firstBankruptcyNum' => 0,
            'lastBigWinNum' => 0,
            'lastFeatureNum' => 0,
            'lastRechargeFeatureNum' => 0,
            'lastWinNum' => 0,
            'lastSpinTime' => 0,
            'tooRichIntervene' => '',
            'bankruptcyInfo' => [
                "hitType" => 0,
                "iCurHitCD" => 0,
                "iLastHitCD" => 0,
                "lastHitType" => 0,
                "iHitBankruptcyBet" => 0,
                "iHitBankruptcyCoin" => 0,
                "iHitBankruptcyDays" => 0,
                "iHitBankruptcyTime" => 0,
                "iHitInterveneTimes" => 0,
                "iHitBankruptcyRate" => 0,
                "isFirstBankruptHit" => false,
                "iHitBankruptcyWinMultiple" => 0,
            ],
        );
    }

    /**
     * 初始化测试用户的统计数据
     */
    public function initAnalysisInInTestMode($uid)
    {
        $analysisInfo = $this->initAnalysisInfo();
        $analysisInfo['totalSpinTimes'] = $analysisInfo['spinTimes'];
        $analysisInfo['SpinInfo']['totalSpinTimes'] = $analysisInfo['spinTimes'];

        return $analysisInfo;
    }

    /**
     * 获取玩家游戏统计数据
     */
    public function getAnalysisInfo($uid)
    {
        $key = $this->getCacheKey($uid);
        $analysisInfo = Dao::redis()->hGet($key, $this->getHashKey());
        $analysisInfo = $analysisInfo ? json_decode($analysisInfo, true) : [];
        if (!$analysisInfo) {
            $analysisInfo = $this->initAnalysisInfo();
        }

        $this->checkAnalysisInfo($analysisInfo);

        return $analysisInfo;
    }

    public function checkAnalysisInfo(&$analysisInfo)
    {
        if (!isset($analysisInfo['totalSpinTimes'])) {
            $analysisInfo['totalSpinTimes'] = $analysisInfo['SpinInfo']['totalSpinTimes'];
        }
    }

    /**
     * 更新玩家游戏统计数据
     */
    public function updateAnalysisInfo($uid, $data)
    {
        $orgInfo = $this->getAnalysisInfo($uid);
        $info = array_merge($orgInfo, $data);
        unset($info['totalSpinTimes']);

        Dao::redis()->hSet($this->getCacheKey($uid), $this->getHashKey(), json_encode($info));
    }

    /**
     * 更新玩家的spin数据
     * @param $uid
     * @param $machineId
     * @param $gameInfo
     */
    public function updateMachineSpinTimes($uid, $machineId, $spinTimes)
    {
        $hKey = $this->getHashKey($machineId);
        $cKey = $this->getCacheKey($uid);
        $analysisInfo = Dao::redis()->hGet($cKey, $hKey);
        $analysisInfo = $analysisInfo ? json_decode($analysisInfo, true) : [];

        $currSpinTimes = $analysisInfo['spinTimes'] ?? 0;
        if ($spinTimes == $currSpinTimes) {
            return;
        }

        $analysisInfo['spinTimes'] = $spinTimes;
        Dao::redis()->hSet($cKey, $hKey, json_encode($analysisInfo));
    }

    /**
     * spin结束时进行数据分析
     */
    public function spinAnalyze($machineId, $betContext, $prizes, $settled, $balance, &$analysisInfo)
    {
        $analysisInfo['lastSpinTime'] = time();

        if ($betContext['cost']) {
            $analysisInfo['profitToday'] -= $betContext['cost'];
            $analysisInfo['SpinInfo']['totalSpinTimes']++;
            $analysisInfo['totalSpinTimes']++;
            if (!empty($prizes['features'])) {
                $analysisInfo['lastFeatureNum'] = $analysisInfo['totalSpinTimes'];
            }
        }

        if ($settled) {
            $totalWin = $betContext['totalWin'];
            if ($totalWin) {
                $analysisInfo['lastWinNum'] = $analysisInfo['totalSpinTimes'];
                $analysisInfo['profitToday'] += $totalWin;
            }

            if ($betContext['winType']) {
                $analysisInfo['lastBigWinNum'] = $analysisInfo['totalSpinTimes'];
            }

            if (!$analysisInfo['firstBankruptcyNum'] && $balance < 100000 && ($balance - $totalWin + $betContext['totalBet']) > 100000) {
                $analysisInfo['firstBankruptcyNum'] = $analysisInfo['totalSpinTimes'];
            }
        }
    }

    /**
     * feature结束时进行数据分析
     */
    public function featureAnalyze($betContext, $balance, &$analysisInfo)
    {
        $totalWin = $betContext['totalWin'];
        if ($totalWin) {
            $analysisInfo['profitToday'] += $totalWin;
        }
    }
}