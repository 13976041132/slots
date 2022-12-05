<?php
/**
 * Slots附加功能
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;

abstract class SlotsAddition extends SlotsIntervene
{
    protected $extraInfo = array();
    protected $spinPrize = array();
    protected $collectInfo = array();

    public function clearBuffer()
    {
        parent::clearBuffer();

        $this->extraInfo = array();
        $this->spinPrize = array();
    }

    /**
     * 获取等级奖励
     */
    public function getLevelPrize()
    {
        $expAdd = $this->betContext['cost'];
        if (!$expAdd) return null;

        $result = Bll::user()->addExp($this->uid, $expAdd);

        $this->userInfo['level'] = $result['level'];
        $this->userInfo['currLevelExp'] = $result['currLevelExp'];
        if (!empty($result['prizes']['coins'])) {
            $this->balance += $result['prizes']['coins'];
        }

        return array(
            'exp' => $this->userInfo['currLevelExp'],
            'level' => $this->userInfo['level'],
            'levelUp' => $result['levelUp'],
            'prizes' => $result['prizes'],
            'betOptions' => $result['levelUp'] ? $this->getNewUnlockedBetOptions() : []
        );
    }

    public function onLevelUp()
    {
        $newestLevel = Bll::user()->getField($this->uid, 'level');

        if ($newestLevel <= $this->userInfo['level']) {
            return;
        }

        $this->userInfo['level'] = $newestLevel;
        $this->userInfo['coinBalance'] = Bll::user()->getCoins($this->uid);
        $this->balance = $this->userInfo['coinBalance'];

        $this->getNewUnlockedBetOptions();
    }

    /**
     * 升级后获取新解锁的下注选项
     */
    protected function getNewUnlockedBetOptions()
    {
        $betOptions = array();
        $maxBet = max($this->betOptions);
        $unlockBetOptions = Bll::machineBet()->getUnlockBetOptionList(
            $this->uid, $this->machineId
        );

        foreach ($unlockBetOptions as $betOption) {
            if ($betOption['totalBet'] > $maxBet) {
                $this->betOptions[$betOption['betMultiple']] = $betOption['totalBet'];
                $this->betOptionList[] = $betOption;
                $betOptions[] = $betOption;
            }
        }

        if ($betOptions) {
            Bll::machineBet()->clearMaxBets($this->uid);
        }

        return $betOptions;
    }

    /**
     * 额外功能数据更新
     */
    protected function updateAdditions($prizes)
    {
        //jackpot进度
        if ($this->hasJackpotProgress()) {
            $this->updateJackpotProgress($prizes);
        }

        //BonusCredit
        $this->updateBonusCredit();

        //更新额外信息
        $this->updateExtraInfo();
    }

    public function getCollectInfo()
    {
        if ($this->collectInfo) {
            return $this->collectInfo;
        }

        $collectInfo = Bll::machineCollect()->getCollectInfo(
            $this->uid, $this->machineId, $this->gameInfo
        );

        $collectInfo['unlockBet'] = $this->getTotalBetByIndex($collectInfo['unlockBetLevel']);

        $this->gameInfo['collectNode'] = $collectInfo['node'];
        $this->gameInfo['collectTarget'] = $collectInfo['target'];
        $this->gameInfo['collectProgress'] = $collectInfo['progress'];
        $this->gameInfo['collectAvgBet'] = $collectInfo['avgBet'];

        $this->collectInfo = $collectInfo;

        return $collectInfo;
    }

    public function updateCollectInfo($data)
    {
        foreach ($data as $key => $value) {
            $this->collectInfo[$key] = $value;
            $gameInfoKey = 'collect' . ucfirst($key);
            if (isset($this->gameInfo[$gameInfoKey])) {
                $this->gameInfo[$gameInfoKey] = $value;
            }
        }

        //刷新收集完成状态
        if (isset($data['progress'])) {
            $this->collectInfo['complete'] = $data['progress'] >= $this->collectInfo['target'];
        }
    }

    public function clearCollectAvgBet()
    {
        $this->updateCollectInfo(array(
            'spinTimes' => 0,
            'betSummary' => 0,
            'avgBet' => 0
        ));
    }

    public function updateBonusCredit()
    {
        //to override
    }

    public function testStat($resultSteps, $prizes, $settled)
    {
        $this->featureWinStat($prizes);
        $this->betResultStats($settled);
        $this->wildStats($resultSteps);

        Bll::slotsTest()->coinAwardStats($prizes);
    }

    public function betResultStats($settled)
    {
        $betContext = array(
            'betTimes' => (int)$settled,
            'totalWin' => $settled ? $this->betContext['totalWin'] : 0,
            'totalBet' => $this->betContext['cost'],
        );

        Bll::slotsTest()->betResultStats($betContext);
    }

    public function featureWinStat($prizes)
    {
        $featureWinInfo = array();
        $features = $prizes['features'] ?: [];
        $triggers = array();
        $hits = array();

        $othersWin = $prizes['coins'] - array_sum($this->featureWinInfo);

        foreach ($features as $featureId) {
            if ($this->betContext['isFreeSpin'] && $featureId != 'jackpot') {
                $featureKey = "{$this->betContext['feature']}>{$featureId}";
            } else {
                $featureKey = $featureId;
            }
            if (isset($this->featureWinInfo[$featureId])) {
                $featureWinInfo[$featureKey] = $this->featureWinInfo[$featureId];
                if (!$this->betContext['isFreeSpin'] && $this->isFreeGame($featureId)) {
                    $featureKey = "{$featureId}>trigger";
                    $featureWinInfo[$featureKey] = $this->featureWinInfo[$featureId];
                }
            } elseif (!$this->betContext['isFreeSpin'] && $this->isFreeGame($featureId)) {
                $featureWinInfo[$featureKey] = 0;
                $hits[$featureId] = 1;
            } else {
                $featureWinInfo[$featureKey] = $othersWin;
                $othersWin = 0;
            }
        }

        //base中奖统计
        if ($othersWin || !$prizes['features']) {
            if ($this->betContext['isFreeSpin']) {
                $featureKey = "{$this->betContext['feature']}>Base";
            } else {
                $featureKey = 'base';
            }
            $featureWinInfo[$featureKey] = $othersWin;
        }

        //累计freeGame总中奖额
        if ($this->betContext['isFreeSpin']) {
            $featureId = $this->betContext['feature'];
            //$featureWinInfo[$featureId] = $prizes['coins'] - $jackpotWin;
            $featureWinInfo[$featureId] = $prizes['coins'];
            $triggers[$featureId] = 0;
            $hits[$featureId] = 0;
        }

        foreach ($featureWinInfo as $featureId => $coins) {
            $featureNames = array_map(function ($_featureId) {
                return $this->getFeatureName($_featureId) ?: $_featureId;
            }, explode('>', $featureId));

            $trigger = $triggers[$featureId] ?? 1;
            $hit = $hits[$featureId] ?? ($coins ? 1 : 0);
            $data = array('base' => array('coins' => $coins, 'trigger' => $trigger, 'hit' => $hit));
            Bll::slotsTest()->featureStats($featureId, implode('>', $featureNames), $data);
        }
    }

    /**
     * wild统计
     */
    public function wildStats($resultSteps)
    {
        if (!$this->isFreeSpin()) return;

        foreach ($resultSteps as $data) {
            $wildCount = 0;
            foreach ($data['elements'] as $element) {
                if (!$this->isWildElement($element['elementId'])) {
                    continue;
                }
                $wildCount++;
            }

            if (!$wildCount) continue;

            $elementsPoint = $this->elementsListToPoint($data['elements']);

            Bll::slotsTest()->featureStats('FG>WildCount', 'FG>WildCount', ['collected' => $wildCount]);

            $wildHitStated = [];
            $wildHitCount = 0;

            foreach ($data['results'] as $result) {
                if ($result['lineRoute']) {
                    foreach ($result['lineRoute'] as $index => $row) {
                        $col = $index + 1;
                        if (isset($wildHitStated[$col][$row]) || !$this->isWildElement($elementsPoint[$col][$row])) {
                            continue;
                        }

                        $wildHitStated[$col][$row] = 1;
                        $wildHitCount++;
                    }
                } else {
                    foreach ($result['elements'] as $index => $_hElementId) {
                        if (!$_hElementId) {
                            continue;
                        }
                        $col = $index + 1;
                        foreach ($elementsPoint[$col] as $row => $_rElementId) {
                            if (isset($wildHitStated[$col][$row]) || !$this->isWildElement($_rElementId)) {
                                continue;
                            }

                            $wildHitStated[$col][$row] = 1;
                            $wildHitCount++;
                        }
                    }
                }
            }

            if ($wildHitCount) {
                Bll::slotsTest()->featureStats('FG>WildHitCount', 'FG>WildHitCount', ['collected' => $wildHitCount]);
            }
        }
    }

    public function getGameExtra()
    {
        return $this->getGameInfo('gameExtra');
    }

    public function getTaskExtra()
    {
        return $this->getGameInfo('taskExtra');
    }

    public function updateGameExtra($extra)
    {
        $this->updateGameInfo(array('gameExtra' => $extra));
    }

    public function updateTaskExtra($extra)
    {
        $this->updateGameInfo(array('taskExtra' => $extra));
    }

    public function updateExtraInfo()
    {
        //to override
    }

    public function getExtraInfo()
    {
        $extraInfo = $this->extraInfo;

        $gameExtra = $this->getGameExtra();

        $extraInfo["M" . $this->machineId] = $gameExtra;

        return $extraInfo;
    }

    /**
     * 将玩家设置为FreeGame状态
     * 通常是通过领取奖励行为触发(不在Spin中)
     */
    public function setFreeGameByAward($featureId, $times, $options = null)
    {
        $this->onFreeGameTriggered($featureId, $times);

        if (!empty($options['totalBet'])) {
            $resumeBet = $this->getTotalBet();
            $this->setTotalBet($options['totalBet'], $resumeBet);
        }

        //机台收集功能中，触发了FreeGame后立即清空收集期间的平均Bet
        $featureName = $this->getFeatureName($featureId);
        if (strpos($featureName, 'Collect') !== false) {
            $this->clearCollectAvgBet();
        }
    }
}