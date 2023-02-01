<?php

namespace FF\Machines;

use FF\Library\Utils\Utils;
use FF\Machines\SlotsModel\SlotsMachine;

class Buffalo extends SlotsMachine
{
    public function checkElements(&$elements)
    {
        if (!$this->isFreeSpin()) return true;

        $elementReelWeights = $this->getElementReelWeights();
        foreach ($elements as $col => $rowElements) {
            $colWildCnt = 0;
            foreach ($rowElements as $row => $elementId) {
                if (!$this->isWildElement($elementId)) continue;
                while ($colWildCnt) {
                    $hitElementId = (string)Utils::randByRates($elementReelWeights[$row]);
                    if ($this->isWildElement($hitElementId)) continue;
                    $elements[$col][$row] = $hitElementId;
                    break;
                }
                $colWildCnt++;
            }
        }

        return true;
    }

    public function checkFeaturePrizes(&$features, &$featurePrizes, $elements)
    {
        if (!$this->isFreeSpin()) {
            return parent::checkFeaturePrizes($features, $featurePrizes, $elements);
        }
        $multiple = 1;
        $featureConfig = $this->getFeatureConfig($this->getCurrFeature());
        foreach ($elements as $rowElements) {
            foreach ($rowElements as $elementId) {
                if (!$this->isWildElement($elementId)) continue;
                $multiple *= Utils::randByRates($featureConfig['itemAwardLimit']['wildMultiple']);
            }
        }

        $multiple = min(27, $multiple);
        $featurePrizes['multiple'] = $multiple;

        return parent::checkFeaturePrizes($features, $featurePrizes, $elements);
    }


}