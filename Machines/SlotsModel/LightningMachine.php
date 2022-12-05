<?php
/**
 * Lightning机台
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Factory\Feature;
use FF\Machines\Features\Lightning;

class LightningMachine extends SlotsMachine
{
    protected $bonusCollected = null;
    protected $bonusValueChecked = false;

    public function clearBuffer()
    {
        $this->bonusCollected = null;
        $this->bonusValueChecked = false;

        parent::clearBuffer();
    }

    /**
     * 判断feature是否是Lightning2
     * 海洋机台特有
     */
    public function isLightning2($featureId)
    {
        if (!$featureId) return false;

        return $this->getFeatureName($featureId) == FEATURE_LIGHTNING_2;
    }

    /**
     * 判断是否触发了Lightning
     */
    public function isLightningTriggered($features)
    {
        if (!$features) return false;

        foreach ($features as $featureId) {
            if ($this->isLightning($featureId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断是否触发了Lightning2
     */
    public function isLightning2Triggered($features)
    {
        if (!$features) return false;

        foreach ($features as $featureId) {
            if ($this->isLightning2($featureId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取多列合并后的列号
     */
    public function getBigCol($sampleId, &$cols = null)
    {
        $cols = [1, 2, 3, 4, 5];

        return 0;
    }

    protected function checkElements(&$elements)
    {
        $bonusCount = 0;
        $elementsCount = $this->elementsCount($elements);
        foreach ($elementsCount as $elementId => $count) {
            if ($this->isBonusElement($elementId)) {
                $bonusCount += $count;
            }
        }

        //不能直接出现满bonus的情况
        $cols = $this->machine['cols'];
        $rows = $this->machine['rows'];
        if ($bonusCount == $cols * $rows) {
            return false;
        }

        return true;
    }

    protected function checkTriggeredFeatures(&$features, $hitResultIds, $elements)
    {
        foreach ($features as $featureId) {
            //不能同时触发FreeGame和Hold&Spin
            if ($this->isLightning($featureId) && $this->isFreeGameTriggered($features)) {
                return false;
            }
        }

        return parent::checkTriggeredFeatures($features, $hitResultIds, $elements);
    }

    public function getElementsValue($elements, $features = array())
    {
        $values = array();

        $hitType = 0;//普通spin中未触发Hold&Spin
        if ($features && $this->isLightningTriggered($features)) {
            $hitType = 1;//普通spin中触发了Hold&Spin
        }

        $isLightning2 = $this->isLightning2Triggered($features);

        if (!$this->isElementsList($elements)) {
            $elements = $this->elementsToList($elements);
        }

        $bonusValue = null;
        $hitJackpots = array();
        foreach ($elements as $element) {
            $elementId = $element['elementId'];
            if (!$this->isBonusElement($elementId)) continue;
            $col = $element['col'];
            $row = $element['row'];
            if ($isLightning2 && $bonusValue) {
                $value = $bonusValue; //freespin中bonus上数值一致，只随机一次
            } else {
                $value = $this->getBonusValue($elementId, $hitType, $col, $hitJackpots);
                if ($this->isJackpotValue($value)) {
                    $hitJackpots[] = $value;
                }
                $bonusValue = $value;
            }
            $values[$col][$row] = $value;
        }

        return $values;
    }

    public function isBonusValue($value)
    {
        if (is_numeric($value)) return true;

        $jackpots = array_column($this->jackpots, null, 'jackpotName');

        if (isset($jackpots[$value])) return true;

        return false;
    }

    /**
     * 由配置数值计算bonus上的数值
     */
    public function calBonusValue($configValue)
    {
        $totalBet = $this->getTotalBet();

        $value = (int)bcmul($configValue, $totalBet);

        return $value;
    }

    /**
     * 由bonus上的数值反推配置值
     */
    public function calBonusConfigValue($value, $totalBet, $betMultiple)
    {
        $configValue = $value / $totalBet;

        return $configValue;
    }

    /**
     * 获取触发feature的bonus元素列表
     */
    public function getBonusCollected($featureId, &$collected)
    {
        $collected = 0;
        $bonusElements = array();

        $bigCol = 0;
        $isLightning2 = $this->isLightning2($featureId);
        $elements = $this->getStepElements();

        foreach ($elements as $element) {
            $col = $element['col'];
            $row = $element['row'];
            $elementId = $element['elementId'];
            if (!$this->isBonusElement($elementId)) continue;
            $collected++;
            if ($isLightning2) {
                if ($col != $bigCol || $row != 1) continue;
            }
            $values = explode(',', $element['value']);
            foreach ($values as $value) {
                if (!$this->isBonusValue($value)) continue;
                $bonusElements[] = array(
                    'elementId' => $elementId,
                    'col' => $col,
                    'row' => $row,
                    'value' => $value
                );
                break;
            }
        }

        return $bonusElements;
    }

    /**
     * 自动完成 feature
     * 测试模式或者机器人 Feature自动完成；
     */
    public function getFeaturePrizesAutoInTesting($featureId, $args = [])
    {
        if ($this->isLightning($featureId)) {
            $steps = $this->getFeaturePlugin($featureId)->autoSpin($args);
            $lastStep = array_pop($steps);
            $prizes = array('coins' => $lastStep['totalWin']);
            $statData = array(
                'collected' => $lastStep['collected'],
                'totalWin' => $lastStep['totalWin'],
                'totalBet' => $this->betContext['totalBet'],
                'isLighting' => true
            );
            if (defined('TEST_ID')) {
                $featureName = $this->getFeatureName($featureId);
                $triggerItemCnt = $this->featureData[$featureId]['triggerItemCount'];
                $fTriggerName = $featureName . 'TriggerNum';

                Bll::slotsTest()->featureStats($fTriggerName, $fTriggerName, ['collected' => $triggerItemCnt]);
                Bll::slotsTest()->featureStats($featureId, $featureName, $statData);

                $this->lightningStatsInTesting($featureId, $steps);
            }
            return $prizes;
        }

        return parent::getFeaturePrizesAutoInTesting($featureId);
    }

    public function onFeatureTriggered($featureId)
    {
        if ($this->isLightning($featureId)) {
            $this->getFeaturePlugin($featureId)->onTrigger();
        }

        parent::onFeatureTriggered($featureId);
    }

    /**
     * @return Lightning|null
     */
    public function getFeaturePlugin($featureId)
    {
        if ($this->isLightning($featureId)) {
            return Feature::lightning($this, $featureId);
        } else {
            return null;
        }
    }

    protected function lightningStatsInTesting($featureId, $steps)
    {
        $lastStep = array_pop($steps);
        $finalElements = $lastStep['finalElements'] ?? [];

        if (!$finalElements) return;

        $elementValueCnt = array_count_values(array_column($finalElements, 'elementId'));
        $featureName = $this->getFeatureName($featureId);

        foreach ($elementValueCnt as $_elementId => $_cnt) {
            $key = "{$featureName}>{$_elementId}_collected";
            Bll::slotsTest()->featureStats($featureId, $key, ['collected' => $_cnt]);
        }
    }

}