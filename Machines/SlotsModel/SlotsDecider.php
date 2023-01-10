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
    protected $bonusElementsReplaceConfig = null;

    public function clearBuffer()
    {
        parent::clearBuffer();

        $this->elementValues = null;
        $this->reelSampleFilter = array();
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
     * 获取机台初始化元素
     */
    public function getInitElements()
    {
        $elements = $this->getRandomElements();
        $this->setElementsValue($elements);

        return $this->elementsToList($elements);
    }

    /**
     * 老虎机转动，给出随机元素
     */
    public function getRandomElements()
    {
        $featureName = 'Base';
        if ($this->isFreeSpin()) {
            $featureName = $this->getFeatureName($this->getCurrFeature());
        }

        if (!$this->elementReelWeights[$featureName]) {
            Log::error("Machine item reel weights config  do not configure {$featureName}", 'slotsGame.log');
            FF::throwException(Code::FAILED);
        }

        $elementReelWeights = $this->elementReelWeights[$featureName];
        $sheetGroup = $this->getSheetGroup();

        while (1) {
            $elements = array();
            foreach ($sheetGroup as $col => $sheets) {
                $scatterDisAble = false;
                foreach (array_keys($sheets) as $row) {
                    while (true) {
                        $elementId = (string)Utils::randByRates($elementReelWeights[$row]);
                        if ($this->isScatterElement($elementId)) {
                            if ($scatterDisAble) continue;
                            $scatterDisAble = true;
                        }

                        $elements[$col][$row] = $elementId;
                        break;
                    }
                }
            }
            if ($this->checkElements($elements)) {
                break;
            }
        }

        return $elements;
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
    public function getBonusValue($elementId, $hitJackpots = array())
    {
        $currFeatureId = $this->getCurrFeature();
        $featureName = $currFeatureId ? $this->getFeatureName($currFeatureId) : 'Base';
        $bonusBallValueRates = Config::get("machine/bonus-ball-value" , $this->machineId);
        $bonusHitRates = $bonusBallValueRates[$featureName] ?? $bonusBallValueRates['Base'];

        //jackpot未解锁或者已经中过了，则去掉该jackpot
        $jackpotPots = $this->getJackpotPots();
        foreach ($bonusHitRates as $value => $weight) {
            if (!$this->isJackpotValue($value)) continue;
            if (!isset($jackpotPots[$value])) {
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
}