<?php
/**
 * 游戏数据分析业务逻辑
 */

namespace FF\Bll;

use FF\App\GameMain\Model\Main\AnalysisModel;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;

class AnalysisBll extends DBCacheBll
{
    protected $fields = array(
        'uid' => ['int', NULL],
        'regCoins' => ['int', 0],
        'spinTimes' => ['int', 0],
        'spinTimesToday' => ['int', 0],
        'freespinTimes' => ['int', 0],
        'freespinMaxTimes' => ['int', 0],
        'winTimes' => ['int', 0],
        'bigWinTimes' => ['int', 0],
        'bankruptTimes' => ['int', 0],
        'greatestWin' => ['int', 0],
        'maxWinMultiple' => ['int', 0],
        'jackpotWin' => ['int', 0],
        'jackpotTimes' => ['int', 0],
        'totalCost' => ['int', 0],
        'totalGained' => ['int', 0],
        'noviceProgress' => ['int', 0],
        'noviceEnded' => ['int', 0],
        'avgBet' => ['int', 0],
        'avgBetRatio' => ['int', 0],
        'lowBetRatioTimes' => ['int', 0],
        'defaultBet' => ['int', 0],
        'lastSpinTime' => ['string', NULL],
        'lastBet' => ['int', 0],
        'recentAvgBet' => ['int', 0],
        'commonUsedBet' => ['int', 0],
        'notBigWinTimes' => ['int', 0],
        'highBetCoolingTime' => ['int', 0],
        'noviceProtect' => ['int', 1],
        'returnProtect' => ['int', 0],
        'rechargeProtect' => ['int', 0],
        'bankruptProtect' => ['string', ''],
        'tooRichIntervene' => ['string', ''],
        'isRelativeBankruptBack' => ['int', 0],
        'lastBalances' => ['string', ''],
        'balanceWaves' => ['string', ''],
        'initBalanceToday' => ['int', 0],
        'profitToday' => ['int', 0],
        'reSpinFreeGameTimes' => ['int', 0],
        'lastMachineId' => ['int', 0],
    );

    /**
     * @return AnalysisModel
     */
    function model($uid)
    {
        return Model::analysis();
    }

    function getCacheKey($uid, $wheres)
    {
        return Keys::analysisInfo($uid);
    }

    protected function redis()
    {
        return Dao::redis('game');
    }

    /**
     * 初始化虚拟用户的统计数据
     */
    public function initVirtualInfo($uid)
    {
        $analysisInfo = array();
        $analysisInfo['uid'] = $uid;

        foreach ($this->fields as $field => $fieldCfg) {
            if (!isset($analysisInfo[$field])) {
                $analysisInfo[$field] = $fieldCfg[1];
            }
        }

        $this->checkAnalysisInfo($analysisInfo);

        return $analysisInfo;
    }

    /**
     * 用户数据初始化入库
     */
    public function initDataInDB($uid, $data)
    {
        $this->model($uid)->init($uid, 200000);
    }

    /**
     * 获取玩家游戏统计数据
     */
    public function getAnalysisInfo($uid, $fields = null)
    {
        $analysisInfo = $this->getCacheData($uid, $fields);
        if (!$analysisInfo) {
            FF::throwException(Code::SYSTEM_BUSY);
        }

        $this->checkAnalysisInfo($analysisInfo);

        return $analysisInfo;
    }

    /**
     * 校正分析信息
     */
    public function checkAnalysisInfo(&$analysisInfo)
    {
        foreach (['lastBalances', 'balanceWaves'] as $key) {
            if (!key_exists($key, $analysisInfo)) continue;
            if ($analysisInfo[$key]) {
                $analysisInfo[$key] = (array)json_decode($analysisInfo[$key], true);
            } else {
                $analysisInfo[$key] = array();
            }
        }
    }

    /**
     * 更新玩家游戏统计数据
     */
    public function updateAnalysisInfo($uid, $data)
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
        }

        return $this->updateCacheData($uid, $data, null, true);

    }

    /**
     * spin结束时进行数据分析
     */
    public function spinAnalyze($machineId, $betContext, $prizes, $settled, $balance, &$analysisInfo)
    {
        $analysisInfo['lastSpinTime'] = now();
        $analysisInfo['lastMachineId'] = $machineId;

        if ($betContext['cost']) {
            //计算平均下注额
            if ($analysisInfo['avgBet']) {
                $betSummary = $betContext['totalBet'] + $analysisInfo['spinTimes'] * $analysisInfo['avgBet'];
                $analysisInfo['avgBet'] = floor($betSummary / ($analysisInfo['spinTimes'] + 1));
            } else {
                $analysisInfo['avgBet'] = $betContext['totalBet'];
            }
            //计算平均下注后手比
            if ($analysisInfo['avgBetRatio']) {
                $betRatioSummary = $betContext['betRatio'] + $analysisInfo['spinTimes'] * $analysisInfo['avgBetRatio'];
                $analysisInfo['avgBetRatio'] = floor($betRatioSummary / ($analysisInfo['spinTimes'] + 1));
            } else {
                $analysisInfo['avgBetRatio'] = $betContext['betRatio'];
            }
            //低后手比下注次数
            if ($betContext['betRatio'] < 30) {
                $analysisInfo['lowBetRatioTimes']++;
            }
            $analysisInfo['lastBet'] = $betContext['totalBet'];
            $analysisInfo['totalCost'] += $betContext['cost'];
            $analysisInfo['profitToday'] -= $betContext['cost'];
            $analysisInfo['spinTimes']++;
            $analysisInfo['spinTimesToday']++;
        }

        if ($betContext['isFreeSpin']) {
            $analysisInfo['freespinTimes']++;
        }
        if ($betContext['isLastFreeSpin']) {
            if ($betContext['spinTimes'] > $analysisInfo['freespinMaxTimes']) {
                $analysisInfo['freespinMaxTimes'] = $betContext['spinTimes'];
            }
        }

        if ($settled) {
            $totalWin = $betContext['totalWin'];
            $winMultiple = floor($totalWin / $betContext['totalBet']);
            if ($totalWin) {
                $analysisInfo['winTimes']++;
                $analysisInfo['totalGained'] += $totalWin;
                $analysisInfo['profitToday'] += $totalWin;
            }
            if ($totalWin > $analysisInfo['greatestWin']) {
                $analysisInfo['greatestWin'] = $totalWin;
            }
            if ($winMultiple > $analysisInfo['maxWinMultiple']) {
                $analysisInfo['maxWinMultiple'] = $winMultiple;
            }
            if ($betContext['winType']) {
                $analysisInfo['bigWinTimes']++;
            }
            if ($betContext['winType'] <= 1) {
                $analysisInfo['notBigWinTimes']++;
            } else {
                $analysisInfo['notBigWinTimes'] = 0;
            }
            if ($balance < 100000 && ($balance - $totalWin + $betContext['totalBet']) > 100000) {
                $analysisInfo['bankruptTimes']++;
            }
        }

        //新手保护期间记录用户经历的波峰和波谷
        //新手样本结束时的资产作为第一个波峰
        if ($settled && $analysisInfo['noviceEnded'] && $analysisInfo['noviceProtect']) {
            $this->balanceWaveAnalyze($balance, $analysisInfo);
        }
    }

    /**
     * feature结束时进行数据分析
     */
    public function featureAnalyze($betContext, $balance, &$analysisInfo)
    {
        $totalWin = $betContext['totalWin'];
        $winMultiple = floor($totalWin / $betContext['totalBet']);

        if ($totalWin) {
            $analysisInfo['totalGained'] += $totalWin;
            $analysisInfo['profitToday'] += $totalWin;
        }
        if ($totalWin > $analysisInfo['greatestWin']) {
            $analysisInfo['greatestWin'] = $totalWin;
        }
        if ($winMultiple > $analysisInfo['maxWinMultiple']) {
            $analysisInfo['maxWinMultiple'] = $winMultiple;
        }
        if ($betContext['winType']) {
            $analysisInfo['bigWinTimes'] += 1;
        }
        if ($betContext['winType'] <= 1) {
            $analysisInfo['notBigWinTimes']++;
        } else {
            $analysisInfo['notBigWinTimes'] = 0;
        }

        if ($analysisInfo['noviceEnded'] && $analysisInfo['noviceProtect']) {
            $this->balanceWaveAnalyze($balance, $analysisInfo);
        }
    }

    /**
     * 资产波动分析
     */
    public function balanceWaveAnalyze($balance, &$analysisInfo)
    {
        $analysisInfo['lastBalances'][] = $balance;
        if (!$analysisInfo['balanceWaves']) {
            $analysisInfo['balanceWaves'][] = [1, $balance];
        }

        $balances = $analysisInfo['lastBalances'];

        if (count($balances) == 1) {
            return;
        } elseif (count($balances) > 3) {
            $balances = array_slice($balances, -3);
        }

        $waveType = 0;
        $newWave = null;
        $lastWave = array_pop($analysisInfo['balanceWaves']);
        $lastWaveType = $lastWave[0];
        $lastWaveBalance = $lastWave[1];

        //判断最近3次资产记录是否形成波峰或波谷
        if (count($balances) == 3) {
            if ($balances[1] > $balances[0] && $balances[1] > $balances[2]) {
                $waveType = 1; //波峰
            } elseif ($balances[1] < $balances[0] && $balances[1] < $balances[2]) {
                $waveType = -1; //波谷
            }
        }

        if (!$waveType) {
            //波峰或波谷的峰值更新
            if ($lastWaveType == 1 && $balance > $lastWaveBalance) {
                $lastWave[1] = $balance;
            } elseif ($lastWaveType == -1 && $balance < $lastWaveBalance) {
                $lastWave[1] = $balance;
            }
        } else {
            $waveBalance = $balances[1];
            if ($lastWaveType != $waveType) {
                //涨幅或跌幅超过30%，则识别为一个新的波峰或波谷[出现0除数，兼容处理]
                $minWaveBalance = min($lastWaveBalance, $waveBalance);
                if ($minWaveBalance != 0 && abs($lastWaveBalance - $waveBalance) / $minWaveBalance >= 0.3) {
                    $newWave = [$waveType, $waveBalance];
                }
            } else {
                //波峰或波谷的峰值更新
                if ($waveType == 1 && $waveBalance > $lastWaveBalance) {
                    $lastWave[1] = $waveBalance;
                } elseif ($waveType == -1 && $waveBalance < $lastWaveBalance) {
                    $lastWave[1] = $waveBalance;
                }
            }
        }

        $analysisInfo['lastBalances'] = $balances;
        $analysisInfo['balanceWaves'][] = $lastWave;
        if ($newWave) {
            $analysisInfo['balanceWaves'][] = $newWave;
        }

        //经历波峰、波谷总个数达到5个，则结束新手保护期
        if (count($analysisInfo['balanceWaves']) >= 3) {
            Log::info([$analysisInfo['uid'], $analysisInfo['balanceWaves']], 'wave.log');
            $analysisInfo['noviceProtect'] = 0;
            $analysisInfo['lastBalances'] = array();
            $analysisInfo['balanceWaves'] = array();
        }
    }
}