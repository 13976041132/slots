<?php
/**
 * Lightning玩法
 */

namespace FF\Machines\Features;

use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;
use FF\Machines\SlotsModel\LightningMachine;
use function Swoole\Coroutine\Http\request;

class Lightning extends BaseFeature
{
    /**
     * @var LightningMachine
     */
    protected $machineObj;

    protected $featureCfg;

    protected $currentTimes;

    protected $timesHit;

    protected $timesResetAble = true;

    protected $initTimes = 3;

    protected function init($args = array())
    {
        if (!empty($args['elements'])) {
            $bonusList = $args['elements'];
        } else {
            $bonusList = $this->machineObj->getBonusCollected($this->featureId, $collected);
        }

        $featureDetail = array(
            'times' => $this->initTimes, 'round' => 1,
            'collected' => 0, 'elements' => array(),
            'timesHit' => 0
        );
        $featureDetail['collected'] = count($bonusList);
        $featureDetail['elements'] = $bonusList;

        return $featureDetail;
    }

    public function onResume()
    {
        return $this->machineObj->getFeatureDetail();
    }

    protected function isSpinAble()
    {
        return true;
    }

    public function onSpin($args = array())
    {
        if (!$this->isSpinAble()) FF::throwException(Code::FAILED);

        $_this = $this->machineObj;
        $featureDetail = $_this->getFeatureDetail();

        if (empty($featureDetail['times'])) FF::throwException(Code::FAILED);

        $isOver = false;
        $hitElements = array();
        $featureDetail['times']--;

        $hitResult = $this->getHitResult($featureDetail);

        if ($hitResult['elements']) {
            $hitElements = $hitResult['elements'];
            $this->statHitElements($hitElements, $hitResult['newCollected'], $featureDetail);
            $maxCollectCount = $this->getMaxCollectCount($featureDetail);
            if ($featureDetail['collected'] < $maxCollectCount) {
                if ($this->timesResetAble && $hitResult['newCollected']) {
                    $featureDetail['times'] = $this->initTimes;
                    $featureDetail['round'] = 1;
                }
            } else {
                $featureDetail['times'] = 0;
            }
        } elseif ($featureDetail['times']) {
            $featureDetail['round']++;
        }

        if ($this->currentTimes == 1) {
            $featureDetail['timesHit'] = $this->timesHit;
        }

        if ($featureDetail['times'] == 0) {
            $isOver = true;
        }

        $result = array(
            'elements' => $hitElements,
            'isOver' => $isOver,
            'bonusWin' => 0,
            'jackpotPrizes' => array(),
            'times' => $featureDetail['times'],
            'collected' => $featureDetail['collected'],
            'winInfo' => array()
        );

        $this->checkLightningResult($result, $featureDetail);

        if ($isOver) {
            $data = $this->settlement($featureDetail);
            $result = array_merge($result, $data);
        } else {
            $_this->setFeatureDetail($featureDetail);
        }

        $this->onSpinCompleted($featureDetail, $result, $isOver);

        return $result;
    }

    /**
     * spin结束时，统计玩家游戏数据，记录spin日志
     */
    public function onSpinCompleted($detail, $result, $settled)
    {
        // 不进行结算标记，存在重复统计问题
        $settled = false;

        $_this = $this->machineObj;

        // 更新 betId
        if (defined('TEST_ID')) {
            $betId = $_this->getBetId() . ':' . Utils::getRandChars(4);
        } else {
            $betId = $_this->renewBetId();
        }

        // 下注次序
        $betSeq = $_this->getAnalysisInfo('spinTimes');

        // 重写下注结果
        $resultSteps = $this->checkResultSteps($detail, $result);

        // 重写 prizes 信息
        $prizes = $this->checkResultPrizes($result);

        // 重写 betContext
        $betContext = $this->checkResultBetContext($_this->getBetContext(), $detail, $result, $settled);

        //下注日志
        Bll::log()->addBetLog(
            $betId, $this->uid, $this->machineId, $_this->getUserInfo('level'),
            $betSeq, $betContext, $resultSteps, [], $prizes, $settled, $_this->getBalance(),
            Bll::session()->get('version')
        );
    }

    /**
     * 重置 prizes 信息
     */
    public function checkResultPrizes($result)
    {
        return array(
            'coins' => isset($result['winInfo']) ? $result['winInfo']['totalWin'] : $result['totalWin'],
        );
    }

    /**
     * 重置下注结果步骤
     */
    public function checkResultSteps($detail, $result)
    {
        $resultSteps = [];
        $machine = $this->get('machine');
        $resultSteps[] = array(
            'step' => 1,
            'elements' => $this->fillEmptySheet($detail['elements'], $machine['cols'], $machine['rows']),
            'prizes' => [
                'elements' => [],
                'features' => [],
                'splitElements' => [],
            ],
            'results' => []
        );
        return $resultSteps;
    }

    /**
     * 填充空位置，用于后台展示结果
     */
    public function fillEmptySheet($elements, $cols, $rows)
    {
        $sheets = [];
        for ($col = 1; $col <= $cols; $col++) {
            for ($row = 1; $row <= $rows; $row++) {
                $sheets[$col][$row] = '';
            }
        }

        foreach ($elements as $element) {
            unset($sheets[$element['col']][$element['row']]);
        }

        foreach ($sheets as $col => $sheet) {
            foreach ($sheet as $row => $val) {
                $elements[] = array(
                    'col' => $col,
                    'row' => $row
                );
            }
        }

        return $elements;
    }

    /**
     * 重写 betContext
     */
    public function checkResultBetContext($betContext, $detail, $result, $settled)
    {
        $betContext['isFreeSpin'] = true;
        $betContext['isLastFreeSpin'] = $settled;
        $betContext['spinTimes'] = $detail['times'];
        $betContext['feature'] = $this->featureId;
        $betContext['featureNo'] = $this->featureNo;
        $betContext['isReFreeSpin'] = $this->timesResetAble && !empty($result['elements']);

        return $betContext;
    }

    /**
     * 统计命中元素个数
     */
    protected function statHitElements($hitElements, $newCollected, &$featureDetail)
    {
        if (!isset($featureDetail['elements'])) {
            $featureDetail['elements'] = array();
        }
        $featureDetail['elements'] = array_merge($featureDetail['elements'], $hitElements);
        $featureDetail['collected'] += $newCollected;
    }

    /**
     * 获取本轮spin命中结果
     */
    public function getHitResult($featureDetail)
    {
        return $this->getHitResultByConfig($featureDetail);
    }

    /**
     * 依据配置生成命中结果
     */
    public function getHitResultByConfig($featureDetail)
    {
        if ($this->initTimes - $featureDetail['times'] == 1) {
            $timesHit = max(1, $featureDetail['timesHit']);
            $config = $this->machineObj->getFeatureConfig($this->featureId);
            $timesResetRound = $config['itemAwardLimit']['resetRound'][$timesHit] ?? [];
            if (!$timesResetRound) {
                $nextTimesHit = rand(1, $this->initTimes);
            } else {
                $nextTimesHit = Utils::randByRates($timesResetRound) + 1;
            }
            $this->timesHit = $nextTimesHit;
        } else {
            $this->timesHit = $featureDetail['timesHit'];
        }

        $this->currentTimes = $this->initTimes - $featureDetail['times'];
        $collected = $featureDetail['collected'];
        $elements = $featureDetail['elements'] ?: array();
        $round = $featureDetail['round'] ?: 1;

        return $this->getHitElements($elements, $collected, $round);
    }

    protected function settlement($featureDetail)
    {
        $jackpotWin = 0;
        $collected = $featureDetail['collected'];
        $jackpotPrizes = $this->getJackpotPrizes($featureDetail['elements'], $collected);
        foreach ($jackpotPrizes as $prize) {
            $jackpotWin += $prize['coins'];
        }

        $bonusWin = $this->getBonusWin($featureDetail);
        $featureWin = $bonusWin + $jackpotWin;

        $winInfo = $this->onEnd($featureWin);

        return array(
            'bonusWin' => $bonusWin,
            'jackpotPrizes' => $jackpotPrizes,
            'winInfo' => $winInfo
        );
    }

    public function autoSpin($args = [])
    {
        $featureDetail = $this->init($args);
        $this->autoChoose($featureDetail);

        $collected = $featureDetail['collected'];
        $isOver = false;
        $steps = [];

        while (1) {
            $featureDetail['times']--;
            $hitElements = array();
            $hitResult = $this->getHitResultByConfig($featureDetail);

            if ($hitResult['elements']) {
                $hitElements = $hitResult['elements'];
                $this->statHitElements($hitElements, $hitResult['newCollected'], $featureDetail);
                $collected = $featureDetail['collected'];
                $maxCollectCount = $this->getMaxCollectCount($featureDetail);
                if ($collected < $maxCollectCount) {
                    if ($this->timesResetAble && $hitResult['newCollected']) {
                        $featureDetail['times'] = $this->initTimes;
                        $featureDetail['round'] = 1;
                        $featureDetail['timesHit'] = 0;
                    }
                } else {
                    $featureDetail['times'] = 0;
                }
            } else {
                if ($featureDetail['times']) {
                    $featureDetail['round']++;
                }
            }

            if ($featureDetail['times'] == 0) {
                $isOver = true;
            } elseif (!$featureDetail['timesHit'] && $featureDetail['times'] < $this->initTimes) {
                $featureDetail['timesHit'] = $this->timesHit;
            }

            $result = array('elements' => $hitElements, 'isOver' => $isOver,
                'times' => $featureDetail['times'], 'collected' => $featureDetail['collected'],
            );

            $this->checkLightningResult($result, $featureDetail);

            $stepInfo = array(
                'elements' => $hitElements,
                'finalElements' => $featureDetail['elements']
            );

            if ($isOver) {
                $jackpotWin = 0;
                $jackpotPrizes = $this->getJackpotPrizes($featureDetail['elements'], $collected);
                foreach ($jackpotPrizes as $prize) {
                    $jackpotWin += $prize['coins'];
                }
                $bonusWin = $this->getBonusWin($featureDetail);
                $stepInfo['collected'] = $collected;
                $stepInfo['totalWin'] = $bonusWin + $jackpotWin + ($result['totalWin'] ?? 0);
            }

            $this->onSpinCompleted($featureDetail, $stepInfo, $isOver);

            $steps[] = $stepInfo;

            if ($isOver) {
                break;
            }
        }

        return $steps;
    }

    /**
     * 获取spin时命中的元素
     */
    protected function getHitElements($elements, $collected, $round)
    {
        $hitResult = array(
            'elements' => array(),
            'newCollected' => 0
        );

        if ($this->timesHit != $this->currentTimes) {
            return $hitResult;
        }

        if (!$holdSpinCfg = $this->getConfig($collected)) return $hitResult;

        $dropCnt = Utils::randByRates($holdSpinCfg['weights']);

        if ($dropCnt == 0) return $hitResult;

        $preCollected = $collected;
        $totalCollected = $collected;

        $validSheets = $this->getValidSheets($elements, $collected);
        shuffle($validSheets);

        for ($i = 1; $i <= $dropCnt; $i++) {
            if (!$validSheets) break;

            $value = '';
            $sheet = array_pop($validSheets);
            $col = $sheet['col'];
            $row = $sheet['row'];
            $elementId = Utils::randByRates($holdSpinCfg['bonusElements']);

            if ($this->machineObj->isBonusElement($elementId)) {
                $value = $this->machineObj->getBonusValue($elementId, []);
            }

            $element = array('elementId' => $elementId, 'col' => $col, 'row' => $row, 'value' => $value);
            $hitResult['elements'][] = $element;
            $newCollected = $this->getNewCollectCount($element);
            $totalCollected += $newCollected;
            if (!$this->isCollectAble($col, $row, $preCollected)) {
                continue;
            }
            $hitResult['newCollected'] += $newCollected;
            $collected += $newCollected;
        }

        return $hitResult;
    }

    protected function getNewCollectCount($element)
    {
        return 1;
    }

    /**
     * 判断某个格子上的bonus是否可收集
     */
    public function isCollectAble($col, $row, $collected)
    {
        return true;
    }

    /**
     * 获取可用的格子
     */
    public function getValidSheets($elements, $collected)
    {
        $_this = $this->machineObj;

        $validSheets = array();
        $sheetGroup = $_this->getSheetGroup($this->featureId);

        $elements = $_this->elementsListToPoint($elements);

        //过滤出可用的格子
        foreach ($sheetGroup as $col => $sheets) {
            foreach ($sheets as $row => $sheet) {
                if (!isset($elements[$col][$row])) {
                    $validSheets[] = $sheet;
                }
            }
        }

        return $validSheets;
    }

    /**
     * 计算已经获得的bonus的总赢得
     */
    protected function getBonusWin($featureDetail)
    {
        $bonusWin = 0;
        $collected = $featureDetail['collected'];

        foreach ($featureDetail['elements'] as $element) {
            if (!$this->isCollectAble($element['col'], $element['row'], $collected)) {
                continue;
            }
            $values = explode(',', $element['value']);
            foreach ($values as $value) {
                if (!is_numeric($value)) continue;
                $bonusWin += (int)$value;
            }
        }

        return $bonusWin;
    }

    /**
     * 领取jackpot奖励
     */
    protected function getJackpotPrizes($elements, $collected = 0)
    {
        $hitJackpots = array();
        $jackpotPrizes = array();

        foreach ($elements as $element) {
            $values = explode(',', $element['value']);

            foreach ($values as $value) {
                if (is_numeric($value) || !$this->machineObj->isJackpotValue($value)) {
                    continue;
                }
                $hitJackpots[] = $value;
            }
        }

        $jackpots = $this->machineObj->getActiveJackpots();

        foreach ($hitJackpots as $jackpotName) {
            $jackpotId = $jackpots[$jackpotName];
            $pot = $this->machineObj->getJackpotAward($jackpotName);
            $jackpotPrizes[] = array(
                'jackpotId' => $jackpotId, 'jackpotName' => $jackpotName, 'coins' => $pot
            );
        }

        return $jackpotPrizes;
    }

    /**
     * 获取元素收集的最大数量
     */
    protected function getMaxCollectCount($featureDetail)
    {
        $options = $this->machineObj->getTriggerOptions($this->featureId);

        if (isset($options['cols']) && isset($options['rows'])) {
            return $options['cols'] * $options['rows'];
        } else {
            $machine = $this->get('machine');
            return $machine['cols'] * $machine['rows'];
        }
    }

    /**
     * 更新结果数据
     */
    protected function checkLightningResult(&$result, &$featureDetail)
    {
        //to override
    }

    /**
     * 获取feature配置项
     * 使用了新的配置
     */
    protected function getConfig($collect)
    {
        if ($this->featureCfg) {
            return $this->featureCfg[$collect] ?? null;
        }

        $configs = Config::get('machine/hold-and-spin', "{$this->machineId}");
        if (!$configs) {
            FF::throwException(Code::SYSTEM_ERROR, "Lightning config for {$this->machineId} is missed");
        }
        $this->featureCfg = $configs;

        return $this->featureCfg[$collect] ?? [];

    }
}