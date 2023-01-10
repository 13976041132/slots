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

        if (!$this->isElementsList($elements)) {
            $elements = $this->elementsToList($elements);
        }

        $hitJackpots = array();
        foreach ($elements as $element) {
            $elementId = $element['elementId'];
            if (!$this->isBonusElement($elementId)) continue;
            $col = $element['col'];
            $row = $element['row'];
            $value = $this->getBonusValue($elementId, $hitJackpots);
            if ($this->isJackpotValue($value)) {
                $hitJackpots[] = $value;
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
     * 获取触发feature的bonus元素列表
     */
    public function getBonusCollected($featureId, &$collected)
    {
        $collected = 0;
        $bonusElements = array();

        $elements = $this->getStepElements();

        foreach ($elements as $element) {
            $col = $element['col'];
            $row = $element['row'];
            $elementId = $element['elementId'];
            if (!$this->isBonusElement($elementId)) continue;
            $collected++;
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