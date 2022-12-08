<?php
/**
 * 老虎机原型
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Factory\Feature;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use GPBClass\Enum\MSG_ID;
use GPBClass\Enum\RET;
use MongoDB\Driver\Session;

class SlotsMachine extends SlotsAddition
{
    protected $stepElements;
    protected $adMultipleInfo;

    /**
     * 清除上次运行的缓存数据
     */
    public function clearBuffer()
    {
        parent::clearBuffer();
        unset($this->stepElements);
    }

    /**
     * 进入机台时的逻辑
     */
    public function onEnter()
    {
        $this->updateGameInfo(array(
            'suggestBetIntervene' => '',
            'enterBalance' => $this->balance,
            'enterTime' => now(),
            'enterCost' => 0,
            'enterWin' => 0,
            'enterSpinTimes' => 0,
        ));

        Bll::session()->save(array('machineId' => $this->machineId));

        //更新在玩状态
        Bll::asyncTask()->addTask(EVENT_USER_STATUS, array(
            'uid' => $this->uid,
            'status' => 'playing',
            'isPlaying' => 1
        ));

        return array();
    }

    /**
     * 退出机台时的逻辑
     */
    public function onExit()
    {
        $data = array();

        //更新在玩状态
        Bll::asyncTask()->addTask(EVENT_USER_STATUS, array(
            'status' => 'playing',
            'uid' => $this->uid,
            'isPlaying' => 0
        ));

        return $data;
    }

    /**
     * 运行老虎机
     */
    public function run($totalBet, $options = array())
    {
        $this->isSpinning = true;

        if (!$this->isSpinAble($options)) {
            $this->isSpinning = false;
            Log::error([$this->betContext['feature'], $this->getFeatureDetail()]);
            FF::throwException(RET::FAILED, 'spinning disabled, feature: ' . $this->betContext['feature']);
        }

        $this->runOptions = $options;
        $this->betId = $this->generateBetId();

        $this->betting($totalBet);

        $result = $this->rolling();

        if (!$this->isVirtualMode) {
            $this->saveAnalysisInfo();
            $this->saveGameInfo();
        }
        $this->clearBuffer();
        $this->initBetContext();
        $this->isSpinning = false;
        $this->runOptions = array();

        return $result;
    }

    /**
     * 老虎机下注
     */
    protected function betting($totalBet)
    {
        $cost = 0;
        $isMaxBet = false;
        $isFreeSpin = false;
        $isLastFreeSpin = false;
        $spinTimes = 1;

        //检查当前是否处于freespin中
        $gameExtra = $this->getGameExtra();
        $featureId = $this->gameInfo['featureId'];
        if (!empty($gameExtra['freespinTimes'])) {
            $gameExtra['freespinTimes']--;
            if (!$gameExtra['freespinTimes']) {
                unset($gameExtra['freespinTimes']);
            }
            $this->updateGameExtra($gameExtra);
            $isFreeSpin = true;
            if (!$featureId) {
                $isLastFreeSpin = true;
            } elseif (!$this->isFreeGame($featureId)) {
                $isLastFreeSpin = true;
            } else {
                $spinTimes = $this->freespinInfo['spinTimes'];
            }
        } elseif ($featureId && $this->isFreeGame($featureId)) {
            $spinTimes = $this->freespinInfo['spinTimes'];
            $totalTimes = $this->freespinInfo['totalTimes'];
            if ($spinTimes < $totalTimes) {
                if ($this->incFreespinTimes()) {
                    $spinTimes++;
                    $isFreeSpin = true;
                    if ($spinTimes == $totalTimes) {
                        $isLastFreeSpin = true;
                    }
                } else {
                    Log::error([$featureId, $this->gameInfo['featureDetail'], $this->freespinInfo], 'feature-error.log');
                    FF::throwException(RET::SYSTEM_BUSY);
                }
            } else {
                Log::error([$featureId, $this->gameInfo['featureDetail'], $this->freespinInfo], 'feature-error.log');
                $this->clearFreespin();
                $this->clearFeature();
                FF::throwException(RET::SYSTEM_BUSY);
            }
        }

        //今日首次spin时的资产
        $lastSpinTime = $this->analysisInfo['lastSpinTime'];
        if (!$lastSpinTime || $lastSpinTime < time() || !$this->analysisInfo['initBalanceToday']) {
            $this->analysisInfo['initBalanceToday'] = (int)$this->balance;
        }

        //非freespin则扣金币
        if (!$isFreeSpin) {
            $totalBets = array_values($this->betOptions);
            $betMultiples = array_flip($this->betOptions);
            if (!$totalBet) $totalBet = (int)$totalBets[0];
            if (!isset($betMultiples[$totalBet])) {
                FF::throwException(RET::RET_TOTAL_BET_INVALID);
            }
            $betMultiple = $betMultiples[$totalBet];
            $minBet = $totalBets[0];
            $maxBet = $this->getMaxBet();
            $lastBet = $this->getTotalBet();
            if ($totalBet > $maxBet && !$this->isVirtualMode) {
                FF::throwException(RET::RET_TOTAL_BET_DISABLED);
            }
            //扣除下注额
            if (!$this->decUserCoins($totalBet)) {
                FF::throwException(RET::RET_COINS_NOT_ENOUGH);
            }
            //加注、减注判断
            if ($this->gameInfo['spinTimes'] > 0) {
                $betRaise = $totalBet > $lastBet ? 1 : ($totalBet < $lastBet ? -1 : 0);
            } else {
                $betRaise = 0;
            }
            $betRatio = floor($this->balance / $totalBet);
            $this->betContext['betRatio'] = $betRatio;
            $this->betContext['totalWin'] = 0;
            if ($totalBet != $lastBet) {
                $this->setTotalBet($totalBet);
                $this->betContext['betRaise'] = $betRaise;
                $this->betContext['lastBet'] = $lastBet;
            }
//            $this->updateBetTimes($totalBet, $lastBet);
            $this->updateAvgBet($totalBet);
            $this->updateJackpotAddition();
            if ($totalBet == $maxBet && $maxBet != $minBet) {
                $isMaxBet = true;
            }
            $cost = $totalBet;
            $this->gameInfo['enterCost'] += $cost;
        } else {
            $betMultiple = $this->gameInfo['betMultiple'];
            $totalBet = $this->gameInfo['totalBet'];
        }

        $this->gameInfo['coinsWin'] = 0;
        if (!$isFreeSpin) {
            $this->gameInfo['spinTimes'] += 1;
            $this->gameInfo['enterSpinTimes'] += 1;
        }

        $this->betContext = array_merge($this->betContext, array(
            'betMultiple' => $betMultiple,
            'totalBet' => $totalBet,
            'isMaxBet' => $isMaxBet,
            'isFreeSpin' => $isFreeSpin,
            'isLastFreeSpin' => $isLastFreeSpin,
            'spinTimes' => $spinTimes,
            'cost' => $cost
        ));
    }

    /**
     * 转动老虎机
     */
    public function rolling()
    {
        //spin前干预检测
        //$this->checkInterveneOnBegin();

        //预触发feature
        $this->preTriggerFeature();

        //移动stick元素
        $stickyElements = $this->moveStickyElements();

        //生成转动结果
        $resultSteps = $this->getRollingSteps();

        //spin奖励汇总
        $totalPrizes = $this->getTotalPrizesFromSteps($resultSteps);

        //更新扩展信息
        $this->updateAdditions($totalPrizes);

        //spin奖励结算
        $settled = $this->settlement($totalPrizes);

        //更新spin分析数据、记录spin日志
        $this->onSpinCompleted($resultSteps, $totalPrizes, $settled);

        //本次spin中奖以及大奖类型
        $winCoins = $totalPrizes['coins'];
        $winType = $this->getWinType($winCoins);

        //结算时的大奖类型
        $totalWin = $this->getTotalWin();
        $totalWinType = $this->getWinType($totalWin);

        //spin后干预检测
        //$this->checkInterveneOnEnd($totalWin, $totalWinType);

        $spinResult = array(
            'cost' => $this->betContext['cost'],
            'totalBet' => $this->betContext['totalBet'],
            'resumeBet' => $this->betContext['resumeBet'],
            'isFreeSpin' => $this->betContext['isFreeSpin'],
            'isLastFreeSpin' => $this->betContext['isLastFreeSpin'],
            'spinTimes' => $this->betContext['spinTimes'],
            'feature' => $this->betContext['feature'],
            'resultSteps' => $resultSteps,
            'stickyElements' => $this->elementsToList($stickyElements),
            'nextMsgIds' => array(),
            'winType' => $winType,
            'totalWinType' => $totalWinType,
            'adMultiple' => $this->betContext['adMultiple'] ?: 0,
            'winCoins' => $winCoins,
            'totalWin' => $totalWin,
            'balance' => $this->balance,
            'settled' => $settled,
        );

        if (defined('TEST_ID')) {
            $spinResult['prizes'] = $totalPrizes;
            return $spinResult;
        }

        //附加消息
        $messages = $this->getAdditionMessages($totalPrizes);
        $spinResult['nextMessages'] = $messages;

        return $spinResult;
    }

    /**
     * 汇总本次spin的奖励
     */
    protected function getTotalPrizesFromSteps($steps)
    {
        $totalPrizes = array(
            'coins' => 0, 'freespin' => 0, 'multiple' => 1, 'features' => []
        );

        foreach ($steps as $stepInfo) {
            $prizes = $stepInfo['prizes'];
            $totalPrizes['coins'] += $prizes['coins'];
            $totalPrizes['freespin'] += $prizes['freespin'];
            $totalPrizes['multiple'] = max($totalPrizes['multiple'], $prizes['multiple']);
            $totalPrizes['features'] = array_merge($totalPrizes['features'], $prizes['features']);
        }

        return $totalPrizes;
    }

    /**
     * spin结束时附加其它功能消息
     */
    protected function getAdditionMessages($prizes)
    {
        $messages = array();

        //jackpot奖励通知
        if (!empty($prizes['jackpotPrizes'])) {
            $messages[MSG_ID::MSG_NTF_JACKPOT_PRIZE] = array(
                'jackpotPrizes' => $prizes['jackpotPrizes'],
            );
        }

        return $messages;
    }

    protected function getRollingSteps()
    {
        $retry = 0;
        while (1) {
            $steps = $this->makeRollingSteps($retry);
            if ($steps) break;
            $retry++;
        }

        return $steps;
    }

    /**
     * 以原始方式转动老虎机，得到中奖结果
     */
    protected function makeRollingSteps($retry = 0)
    {
        $this->resetBuffer();
        $rollingSteps = array();
        //生成随机轴元素
        $elements = $this->getRandomElements();
        //ReSpin时会锁定轴元素
        $elementsLocked = $this->getFeatureDetail('elementsLocked');
        if ($elementsLocked) {
            $elements = $this->elementsMerge($elements, $elementsLocked);
        }

        //多Slot时，初始Slot数量
        $slotNum = $this->getFeatureDetail('slotNum');
        $this->slotNum = $slotNum ?: 1;

        while ($elements) {
            //计算中奖结果
            $hitResultIds = $this->getHitResultIds($elements);
            $features = $this->getTriggerFeatures($hitResultIds, $elements);

            //检查、校正触发的feature
            $isValid = $this->checkTriggeredFeatures($features, $hitResultIds, $elements);
            if (!$isValid && $this->checkRetryTimes($retry, -1)) return false;

            //结合触发的feature校正轴元素
            if ($features) {
                $this->checkElementsWithFeature($elements, $features);
            }

            //结合触发的feature检查中奖结果
            $isValid = $this->checkHitResultWithFeature($hitResultIds, $features);
            if (!$isValid && $this->checkRetryTimes($retry, -2)) return false;

            //计算feature奖励
            $featurePrizes = $this->getFeaturePrizes($features, $hitResultIds, $elements);

            //检查、校正feature奖励
            $isValid = $this->checkFeaturePrizes($features, $featurePrizes, $elements);
            if (!$isValid && $this->checkRetryTimes($retry, -3)) return false;

            //feature强制触发or不触发检查
            $isValid = $this->checkForceTriggeredFeatures($features, $retry);
            if (!$isValid && $this->checkRetryTimes($retry, -4)) return false;

            //构建中奖结果信息
            $hitResult = $this->makeHitResult($hitResultIds, $featurePrizes);

            $elementsList = $this->elementsToList($elements);

            //设置元素上的额外值
            $elementsValue = $this->getElementsValue($elementsList, $features);
            $this->setElementsValue($elementsList, $elementsValue, $featurePrizes['values']);

            $this->stepElements[$this->step] = $elementsList;

            //测试模式Feature自动完成
            if ($features && defined('TEST_ID')) {
                $this->autoPlayFeatureInTesting($features, $featurePrizes);
            }

            //奖励汇总
            $prizes = $this->getHitPrizes($hitResult, $features, $featurePrizes);

            $rollingSteps[] = array(
                'step' => $this->step,
                'elements' => $elementsList,
                'prizes' => $prizes,
                'results' => $hitResult,
                'totalWin' => $this->betContext['totalWin'],
            );

            if ($this->hasEliminate) { //消除玩法
                $elements = $this->elementsElimination($hitResult, $elements);
            } else if ($this->step < $this->slotNum) { //多 Slot 玩法
                $elements = $this->getRandomElements();
            } else {
                $elements = null;
            }

            $this->elementValues = null;
            $this->step++;
        }

        return $rollingSteps;
    }

    protected function resetBuffer()
    {
        $this->step = 1;
        $this->elementValues = null;
        $this->stepElements = array();
        $this->featureWinInfo = array();
    }

    /**
     * 检查重试次数，防止死循环
     */
    protected function checkRetryTimes($retry, $code)
    {
        if (!$this->isVirtualMode && $retry > 1000) {
            Log::info("stop retry after 1000 times, code = {$code}", 'slotsGame.log');
            return false;
        }
        if ($this->isVirtualMode && $retry > 5000) {
            cli_exit("stop retry after 1000 times, code = {$code}");
        }

        if ($retry && $retry % 100 == 0) {
            Log::info("retry = {$retry}, code = {$code}, runOptions = " . json_encode($this->runOptions), 'slotsGame.log');
        }

        //恢复TotalWin
        $this->betContext['totalWin'] = $this->gameInfo['totalWin'];

        return true;
    }

    /**
     * 玩家下注扣除TotalBet
     */
    protected function decUserCoins($totalBet)
    {
        if ($this->isVirtualMode) {
            if ($this->balance < $totalBet) {
                return false;
            }
            $this->balance -= $totalBet;
        } else {
            $result = Bll::user()->decCoins($this->uid, $totalBet, 'SlotsBetting');
            if (!$result) {
                return false;
            }
            $this->balance = Bll::user()->getCoins($this->uid);
        }

        return true;
    }

    /**
     * 结算时给玩家账户加金币
     */
    public function addUserCoins($coinsWin)
    {
        if (!$coinsWin) return;

        Bll::user()->addCoins($this->uid, $coinsWin, 'SlotsWin', $balance);

        $this->balance = $balance;

        //记录进入机台后赢得的金币数
        $this->gameInfo['enterWin'] += $coinsWin;
    }

    /**
     * 预触发feature
     */
    protected function preTriggerFeature()
    {
        $preFeatures = $this->getPreTriggerFeature();
        $this->betContext['preFeatures'] = $preFeatures;
        $this->featureOptionsInit = false;

        $this->checkPreFeature();
    }

    /**
     * 生成中奖结果
     */
    protected function makeHitResult($hitResultIds, $featurePrizes)
    {
        if (!$hitResultIds) return array();

        $hitResult = array();

        //计算中奖倍率
        $betMultiple = $this->betContext['betMultiple'];
        $multipleAward = max(1, $featurePrizes['multiple']);

        ksort($hitResultIds, SORT_NUMERIC);

        $k = 0;

        foreach ($hitResultIds as $lineId => $resultId) {
            $k++;
            $lineMultipleAward = $multipleAward;
            if (isset($featurePrizes['multiples'][$lineId])) {
                $lineMultipleAward *= max(1, $featurePrizes['multiples'][$lineId]);
            }
            $resultCount = $this->paylines ? 1 : (int)(explode(':', $lineId)[1]);
            if (!$this->paylines) $lineId = (string)$k;
            if (!isset($this->paytable[$resultId])) continue;
            $prize = is_numeric($this->paytable[$resultId]['prize']) ? $this->paytable[$resultId]['prize'] : 0;
            $winCoins = $prize * $betMultiple * $resultCount * $lineMultipleAward;
            $routes = $this->paylines ? $this->paylines[$lineId]['route'] : [];
            $elements = $this->paytable[$resultId]['elements'];
            $hitResult[] = array(
                'lineId' => $lineId,
                'resultId' => $resultId,
                'resultCount' => $resultCount,
                'lineRoute' => $routes,
                'elements' => $elements,
                'prizes' => array(
                    'coins' => $winCoins,
                    'multiple' => $lineMultipleAward,
                )
            );
        }

        return $hitResult;
    }

    /**
     * 汇总中奖奖励
     */
    public function getHitPrizes(&$hitResult, $features, $featurePrizes)
    {
        $betMultiple = $this->betContext['betMultiple'];
        $sampleBetMultiple = $this->betContext['betMultiple'];

        if ($betMultiple != $sampleBetMultiple) {
            $featurePrizes['coins'] = $featurePrizes['coins'] * $betMultiple / $sampleBetMultiple;
        }

        $splitElements = array();
        foreach ($featurePrizes['splitElements'] as $featureId => $elements) {
            $splitElements[] = array(
                'featureId' => $featureId,
                'elements' => $this->elementsToList($elements)
            );
        }

        $prizes = array(
            'coins' => $featurePrizes['coins'],
            'freespin' => $featurePrizes['freespin'],
            'multiple' => $featurePrizes['multiple'],
            'elements' => $this->elementsToList($featurePrizes['elements']),
            'splitElements' => $splitElements,
            'features' => $features,
        );

        if ($featurePrizes['elements'] && $featurePrizes['values']) {
            $this->elementValues = null;
            $this->setElementsValue($prizes['elements'], $featurePrizes['values']);
        }

        foreach ($hitResult as &$result) {
            $lineWin = $result['prizes']['coins'];
            if ($betMultiple != $sampleBetMultiple) {
                $lineWin = $lineWin * $betMultiple / $sampleBetMultiple;
                $result['prizes']['coins'] = $lineWin;
            }
            $prizes['coins'] += $lineWin;
        }

        if ($prizes['coins']) {
            $this->betContext['totalWin'] += $prizes['coins'];
        }

        if ($prizes['elements']) {
            shuffle($prizes['elements']);
        }

        return $prizes;
    }

    /**
     * 获取本次spin某一步的元素列表
     */
    public function getStepElements($step = null)
    {
        if (!$this->stepElements) return [];

        if (!$step) {
            $step = count($this->stepElements);
        }

        $step = max(1, $step);

        return $this->stepElements[$step];
    }

    public function getAdMultiple()
    {
        if ($this->adMultipleInfo && $this->adMultipleInfo['times'] > 0) {
            $times = --$this->adMultipleInfo['times'];
            $multiple = $this->adMultipleInfo['multiple'];
            if (!$times) {
                $this->adMultipleInfo = null;
            }
            return $multiple;
        } else {
            return 0;
        }
    }

    /**
     * 结算-发放中奖奖励
     */
    protected function settlement(&$prizes)
    {
        //jackpot奖励
        $prizes['jackpotWin'] = 0;
        $prizes['jackpotPrizes'] = $this->getJackpotPrize();
        foreach ($prizes['jackpotPrizes'] as $jackpotPrize) {
            $prizes['jackpotWin'] += $jackpotPrize['coins'];
        }

        //处理已触发的feature
        $this->dealWithTriggeredFeature($prizes['features'], $prizes['freespin']);

        //判定本次spin是否结算
        $settlement = $this->gameInfo['featureId'] ? false : true;
        $settlement = $this->checkSettlement($settlement, $prizes);
        $totalWin = $this->betContext['totalWin'];

        if ($settlement) {
            //中奖赢取金币结算
            $this->coinsSettlement($totalWin);
            $this->gameInfo['betId'] = '';
        } else {
            //保存本次spin赢得金币数与当前TotalWin
            $this->updateGameInfo(array('coinsWin' => $prizes['coins']));
            $this->setTotalWin($totalWin);
            $this->gameInfo['betId'] = $this->betId;
        }

        $this->gameInfo['lastSpinElements'] = $this->getStepElements();

        return $settlement;
    }

    /**
     * 金币结算
     */
    public function coinsSettlement(&$totalWin)
    {
        $winType = $this->getWinType($totalWin);
        $this->betContext['winType'] = $winType;

        if (!$this->isVirtualMode) {
            $this->addUserCoins($totalWin);
        } else {
            $this->balance += $totalWin;
        }

        //feature异步结算
        if (!$this->isSpinning) {
            $this->betContext['totalWin'] = $totalWin;
            Bll::analysis()->featureAnalyze($this->betContext, $this->balance, $this->analysisInfo);
            //$this->checkInterveneOnEnd($totalWin, $winType);
        }

        return $winType;
    }

    /**
     * spin结束时，统计玩家游戏数据，记录spin日志
     */
    public function onSpinCompleted($resultSteps, $prizes, $settled)
    {
        //游戏数据统计分析
        Bll::analysis()->spinAnalyze($this->machineId, $this->betContext, $prizes, $settled, $this->balance, $this->analysisInfo);

        //下注次序
        $betSeq = $this->analysisInfo['spinTimes'];

        //下注日志
        Bll::log()->addBetLog(
            $this->betId, $this->uid, $this->machineId, $this->userInfo['level'],
            $betSeq, $this->betContext, $resultSteps, $this->getExtraLog(), $prizes, $settled, $this->balance,
            Bll::session()->get('version')
        );

        //强制更新进入机台后的输赢
        $updates = array(
            'enterCost' => $this->gameInfo['enterCost'],
            'enterWin' => $this->gameInfo['enterWin'],
            'enterSpinTimes' => $this->gameInfo['enterSpinTimes'],
        );
        $this->updateGameInfo($updates);

        //测试统计
        if (defined('TEST_ID')) {
            $this->testStat($resultSteps, $prizes, $settled);
        }
    }

    /**
     * 销毁机台实例时的逻辑
     */
    public function onDestroy()
    {
        $instanceId = $this->getInstanceId();
        Log::info("clear machine instance, instanceId = {$instanceId}", 'slotsGame.log');

        //清除机台内的所有feature实例
        Feature::clearInstancesByMachine($instanceId);

        //保存游戏数据
        $this->saveAnalysisInfo();
        $this->saveGameInfo();
    }


    /**
     * 获取扩展信息，用于存入下注日志
     */
    public function getExtraLog()
    {
        return [];
    }

}