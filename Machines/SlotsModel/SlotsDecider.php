<?php
/**
 * 老虎机决策器
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;

abstract class SlotsDecider extends SlotsFeature
{
    protected $elementValues = null;
    protected $reelSampleFilter = array();
    protected $bonusConfig = array();
    protected $bonusElementsReplaceConfig = null;

    public function clearBuffer()
    {
        parent::clearBuffer();

        $this->elementValues = null;
        $this->reelSampleFilter = array();
        $this->bonusConfig = array();
        $this->bonusElementsReplaceConfig = null;
    }

    /**
     * 进入机台时推荐bet
     */
    public function getSuggestBetOnEnter()
    {
        $suggestBet = $this->calSuggestBet();
        $this->updateGameInfo(array('suggestBet' => $suggestBet));

        if ($this->gameInfo['featureId']) {
            $betMultiple = $this->gameInfo['betMultiple'];
            $totalBet = $this->gameInfo['totalBet'];
        } else {
            $betMultiple = $this->findBetMultiple($suggestBet);
            $totalBet = $suggestBet;
        }

        return [$betMultiple, $totalBet];
    }

    /**
     * 获取用户当前可下注的最大下注额
     */
    public function getMaxBet()
    {
        return array_last($this->betOptions);
    }

    /**
     * 获取用户当前可下注的最大下注额
     */
    public function getUnlockMaxBet()
    {
        $betOptions = array_column($this->betOptionList, 'totalBet', 'betMultiple');
        return array_last($betOptions);
    }

    /**
     * 获取机台默认下注额
     */
    public function getDefaultBet()
    {
        $defaultBet = $this->gameInfo['defaultBet'];

        if ($defaultBet) {
            $defaultBet = $this->getNearByBet($defaultBet);
        }

        return $defaultBet;
    }

    /**
     * 获取机台推荐下注额
     */
    public function getSuggestBet()
    {
        if (!$this->gameInfo['suggestBet']) {
            $this->gameInfo['suggestBet'] = $this->calSuggestBet();
        }

        return $this->gameInfo['suggestBet'];
    }

    /**
     * 计算推荐的下注额
     */
    public function calSuggestBet()
    {
        return $this->getMaxBet();
    }

    /**
     * 根据指定下注额匹配最接近的下注额
     */
    public function getClosestBet($totalBet)
    {
        $closestBet = Bll::machineBet()->getClosestBet($this->uid, $this->betOptions, $totalBet);

        return $closestBet;
    }

    /**
     * 根据推荐下注额匹配最接近的下注选项(向下取)
     */
    public function getNearByBet($suggestBet)
    {
        $nearByBet = Bll::machineBet()->getNearByBet($this->uid, $this->betOptions, $suggestBet);

        return $nearByBet;
    }

    /**
     * 获取当前使用的样本ID
     */
    public function getCurrSampleId()
    {
        $sampleId = $this->betContext['sampleId'];

        if (!$sampleId) {
            $sampleId = $this->getFeatureDetail('sampleId');
            if (!$sampleId) {
                $sampleId = $this->getReelSample();
            }
        }

        return $sampleId;
    }

    /**
     * 获取bonus元素替换配置
     */
    public function getBonusElementsReplaceConfig()
    {
        return [];
    }

    /**
     * 获取机台初始化元素
     */
    public function getInitElements($featureId = '')
    {
        return [];
    }

    /**
     * 老虎机转动，给出随机元素
     */
    public function getRandomElements()
    {
        if ($this->betContext['feature']) {
            if ($elements = $this->rollByFeature()) {
                return $elements;
            }
        }

        if (empty($this->betContext['sampleId'])) {
            $sampleId = $this->getReelSample(false, $sampleGroup);
            if (!$sampleId) {
                Log::error("machineId: {$this->machineId}, betMultiple: {$this->betContext['betMultiple']}, betRatio: {$this->betContext['betRatio']}", 'sample.log');
                FF::throwException(Code::SYSTEM_ERROR, "failed to find an available sample");
            }
            $this->betContext['sampleGroup'] = $sampleGroup;
            $this->betContext['sampleId'] = $sampleId;
        } else {
            $sampleId = $this->betContext['sampleId'];
        }

        $this->initFeatureOptions();

        return $this->rollByReelSample($sampleId);
    }

    /**
     * 获取当前适用的转轴样本组
     */
    public function getReelSampleGroup($sampleGroup = null)
    {
        if (!$sampleGroup) {
            if (defined('TEST_SAMPLE_GROUP')) {
                $sampleGroup = TEST_SAMPLE_GROUP;
            } elseif (!empty($this->runOptions['sampleGroup'])) {
                $sampleGroup = $this->runOptions['sampleGroup'];
            } else {
                $sampleGroup = $this->gameInfo['sampleGroup'] ?: 'Normal';
            }
        }

        return $sampleGroup;
    }

    /**
     * 获取一个转轴样本
     */
    public function getReelSample($forNextSpin = false, &$sampleGroup = null, $featureId = '')
    {
        if (defined('TEST_SAMPLE_ID')) return TEST_SAMPLE_ID;

        if ($forNextSpin) {
            $currFeature = $this->gameInfo['featureId'];
        } elseif (!$featureId) {
            $currFeature = $this->betContext['feature'];
        } else {
            $currFeature = $featureId;
        }

        //指定了特定的feature专用轴
        if ($currFeature && $sampleId = $this->getFeatureDetail('sampleId')) {
            return $sampleId;
        }

        if (!$forNextSpin && !empty($this->betContext['preFeatures'])) {
            $preFeature = $this->getActivatedFeature($this->betContext['preFeatures']);
        } else {
            $preFeature = '';
        }

        //分离出feature专用样本
        $samplesForCommon = array();
        $samplesForFeature = array();
        $samplesForPreFeature = array();
        $samplesForPreFeatureFurther = array();
        $sampleGroups = Config::get('machine/reel-sample-groups', $this->machineId);

        //当前使用的样本组
        if (!$sampleGroup) {
            $sampleGroup = $this->getReelSampleGroup();
        }

        if (empty($sampleGroups[$sampleGroup])) {
            $sampleGroup = 'Normal';
        }

        $prefix = isset($this->reelSampleFilter['prefix']) ? $this->reelSampleFilter['prefix'] : 'S';
        if ($forNextSpin) $prefix = 'S';

        foreach ($sampleGroups[$sampleGroup] as $sampleId) {
            $sample = $this->samples[$sampleId];
            if (substr($sampleId, 0, strlen($prefix)) !== $prefix) {
                continue;
            }
            if ($sample['betLevel']) { //适配下注档位
                $betPercent = $this->calcBetPercent($this->getTotalBet());
                if (!Utils::isValueMatched($betPercent, $sample['betLevel'])) {
                    continue;
                }
            }

            if (!empty($sample['options']) && !$this->matchSampleOptions($sample['options'])) {
                continue;
            }
            if ($sample['feature']) {
                if ($currFeature && in_array($currFeature, $sample['feature'])) {
                    $samplesForFeature[] = $sample;
                }
                if ($preFeature && in_array($preFeature, $sample['feature'])) {
                    $samplesForPreFeature[] = $sample;
                }
                if ($currFeature && $preFeature && in_array($currFeature . '>' . $preFeature, $sample['feature'])) {
                    $samplesForPreFeatureFurther[] = $sample;
                }
            } else {
                $samplesForCommon[] = $sample;
            }
        }

        $samplesForPreFeature = $samplesForPreFeatureFurther ?: $samplesForPreFeature;
        $samples = $samplesForPreFeature ?: ($samplesForFeature ?: $samplesForCommon);

        if (!$samples) return '';

        if (count($samples) > 1) {
            $weights = array_column($samples, 'weight', 'sampleId');
            $sampleId = Utils::randByRates($weights);
        } else {
            $sampleId = $samples[0]['sampleId'];
        }

        return $sampleId;
    }

    /**
     * 匹配样本适配选项
     */
    public function matchSampleOptions($options)
    {
        return true;
    }

    /**
     * 按真轴样本方式转动老虎机
     */
    public function rollByReelSample($sampleId, $startPos = null, $replaceStack = true)
    {
        if (empty($this->sampleItems[$sampleId])) {
            FF::throwException(Code::SYSTEM_ERROR, "SampleItem is miss for {$this->machineId}/{$sampleId}");
        }

        $sheetGroup = $this->getSheetGroup();
        $sampleItems = &$this->sampleItems[$sampleId];

        while (1) {
            $elements = array();
            $values = array();
            foreach ($sheetGroup as $col => $sheets) {
                if (is_string($sampleItems[$col])) {
                    $sampleItems[$col] = explode(',', $sampleItems[$col]);
                }
                $count = count($sampleItems[$col]);
                if ($startPos === null) {
                    $pos = mt_rand(0, $count - 1);
                } else {
                    $pos = $startPos;
                }
                $elements[$col] = array();
                foreach ($sheets as $row => $sheet) {
                    $index = $pos + $row - 1;
                    if ($index >= $count) {
                        $index = $index - $count;
                    }
                    $elementId = (string)$sampleItems[$col][$index];
                    $elements[$col][$row] = $elementId;
                }
            }
            if ($this->checkElements($elements)) {
                break;
            }
        }

        if ($replaceStack) {
            $this->gameInfo['stacks'] = array();
            $this->elementValues = $values;
        }

        return $elements;
    }

    /**
     * 特殊feature中分配转轴元素
     */
    protected function rollByFeature()
    {
        return array();
    }

    /**
     * 元素消除玩法
     */
    protected function elementsElimination($hitResult, $elements)
    {
        $eliminated = $this->doElimination($hitResult, $elements);

        if (!$eliminated) return null;

        $this->dropReelElementsAfterElimination($elements);
        $this->dropNewElementsFromTop($elements);

        return $elements;
    }

    /**
     * 执行元素消除
     */
    protected function doElimination($hitResult, &$elements)
    {
        //to override
        return false;
    }

    /**
     * 元素消除后，剩余轴元素下落
     */
    protected function dropReelElementsAfterElimination(&$elements)
    {
        foreach ($elements as $col => $_elements) {
            ksort($_elements, SORT_NUMERIC);
            $_elements = array_filter($_elements);
            $elements[$col] = array();
            if (!$_elements) continue;
            $count = count($_elements);
            if ($count == $this->machine['rows']) {
                $elements[$col] = $_elements;
                continue;
            }
            for ($row = 1; $row <= $count; $row++) {
                $elements[$col][$row] = array_shift($_elements);
            }
        }
    }

    /**
     * 从机台顶部掉落新元素-用于消除玩法
     */
    protected function dropNewElementsFromTop(&$elements)
    {
        //to override
    }

    /**
     * 检查随机出来的元素是否有效
     */
    protected function checkElements(&$elements)
    {
        return true;
    }

    /**
     * 结合中奖feature校正轴元素
     */
    protected function checkElementsWithFeature(&$elements, $features)
    {
        //to override
    }

    /**
     * 检查中奖结果、中奖feature
     */
    protected function checkHitResultWithFeature($hitResultIds, $features)
    {
        if (!$hitResultIds) {
            //若设置了必中奖然而未中奖，则重新转
            if (isset($this->runOptions['hit']) && $this->runOptions['hit'] && $this->step == 1) {
                return false;
            }
            //若中了掉落元素的feature但没有payline中奖，则重新转
            $featureId = null;
            foreach ($features as $_featureId) {
                if ($this->featureGames[$_featureId]['itemAward']) {
                    $featureId = $_featureId;
                    break;
                }
            }
            if ($featureId) {
                $triggerOptions = $this->getTriggerOptions($featureId);
                $mustHit = $triggerOptions['mustHit'] ?? false;
                if ($mustHit) {
                    return false;
                }
            }
        } else {
            //若设置了必不中奖然而却中奖，则重新转
            if (isset($this->runOptions['hit']) && !$this->runOptions['hit']) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查玩家当前是否可下注
     */
    public function isSpinAble($options = array())
    {
        //feature状态检查
        $featureId = $this->getCurrFeature();
        if (!$featureId) return true;
        if (!$this->isFreeGame($featureId)) return false;
        if ($this->getFeatureDetail('wheelId')) return false;
        if ($this->isChooseMode($featureId) && !$this->getFeatureDetail('choosed')) return false;

        return true;
    }

    /**
     * 进一步检查本次spin是否进行结算
     */
    protected function checkSettlement($settlement, &$prizes)
    {
        return $settlement;
    }

    /**
     * 获取bonus配置项
     */
    protected function getBonusConfig($key)
    {
        if ($this->bonusConfig) {
            return $this->bonusConfig[$key];
        }

        //获取当前轴样本下的bonus配置组
        $machineId = $this->machineId;
        //TODO
        if (!isset($sampleId)) {
            throw new \Exception('SampleId is empty', Code::SYSTEM_ERROR);
        }
        $configs = Config::get('machine/bonus-value', "{$machineId}/{$sampleId}");
        if (!$configs) {
            throw new \Exception("Bonus value config for {$machineId}/{$sampleId} is missed", Code::SYSTEM_ERROR);
        }

        //根据当前下注额匹配对应的配置
        $totalBetLevel = $this->getTotalBetIndex() + 1;
        foreach ($configs as $config) {
            if (empty($config['activeBetLevel']) || Utils::isValueMatched($totalBetLevel, $config['activeBetLevel'])) {
                $this->bonusConfig = $config;
                break;
            }
        }

        if (!$this->bonusConfig) {
            FF::throwException(Code::SYSTEM_ERROR, "Bonus value config for {$machineId}/{$sampleId}/{$totalBetLevel} is missed");
        }

        return $this->bonusConfig[$key];
    }

    /**
     * 判断bonus值是否是jackpot
     */
    public function isJackpotValue($value)
    {
        if (is_numeric($value)) return false;

        $jackpotType = $this->getJackpotType($value);

        return $jackpotType == 'Jackpot';
    }

    /**
     * 生成bonus上的数值
     */
    public function getBonusValue($elementId, $hitType, $col, $hitJackpots = array())
    {
        $bonusHitRates = $this->getBonusConfig('bonusHitRates');
        $bonusHitRates = json_decode($bonusHitRates[$hitType], true);

        //部分机台有多个bonus，是按元素ID配置
        if (isset($bonusHitRates[$elementId])) {
            $bonusHitRates = $bonusHitRates[$elementId];
        }

        //部分机台的bonus值是按列配置
        if (isset($bonusHitRates[$col]) && is_array($bonusHitRates[$col])) {
            $bonusHitRates = $bonusHitRates[$col];
        }

        //jackpot未解锁或者已经中过了，则去掉该jackpot
        $jackpotPots = $this->getJackpotPots();
        foreach ($bonusHitRates as $value => $weight) {
            if (!$this->isJackpotValue($value)) continue;
            if (!isset($jackpotPots[$value])) {
                unset($bonusHitRates[$value]);
            } elseif (!$this->jackpotHitRepeatedAble && in_array($value, $hitJackpots)) {
                unset($bonusHitRates[$value]);
            }
        }

        $value = Utils::randByRates($bonusHitRates);

        if (is_numeric($value)) {
            $value = $this->calBonusValue($value);
        }

        return (string)$value;
    }

    /**
     * 由配置数值计算bonus上的数值
     */
    public function calBonusValue($configValue)
    {
        return $configValue;
    }

    /**
     * 设置元素上的附加值(一般用作wild翻倍、bonus)
     */
    public function setElementsValue(&$elements, $elementValues = array(), $featureValues = array(), $merge = true)
    {
        if ($this->elementValues) {
            $elementValues = $this->elementsValueMerge($this->elementValues, $elementValues, $merge);
        }

        $values = $this->elementsValueMerge($elementValues, $featureValues, $merge);

        $this->elementValues = $values;

        if (!$values) return;

        foreach ($elements as &$element) {
            $col = $element['col'];
            $row = $element['row'];
            if (isset($values[$col][$row])) {
                $element['value'] = $values[$col][$row];
            }
        }
    }

    /**
     * 获取中奖类型
     */
    public function getWinType($coinsWin)
    {
        if (!$coinsWin) return 0;

        $winType = 0;
        $winMultiple = round($coinsWin / $this->betContext['totalBet'], 2);
        $winMultiples = json_decode($this->machine['winMultiples'], true);

//        $winMultiplesCfg = $this->getAbTestParameter('winMultiples');
//        if ($winMultiplesCfg) {
//            $winMultiples = json_decode($winMultiplesCfg, true);
//        }

        foreach ($winMultiples as $k => $multiple) {
            if ($winMultiple >= $multiple) {
                $winType = $k + 1;
            }
        }

        return $winType;
    }

    public function getBetGear($totalBet)
    {
        $closestBet = $this->getClosestBet($totalBet);
        $totalBets = array_values($this->betOptions);
        $betGear = array_search($closestBet, $totalBets) + 1;

        return $betGear;
    }

    /**
     * 获取对应 WinType 的倍数值
     */
    public function getWinMultiByWinType($winType)
    {
        if ($winType <= 0) return 0;

        $winMultiples = json_decode($this->machine['winMultiples'], true);

        return isset($winMultiples[$winType - 1]) ? $winMultiples[$winType - 1] : max($winMultiples);
    }


}