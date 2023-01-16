<?php

namespace FF\Machines;

use FF\Library\Utils\Utils;
use FF\Machines\SlotsModel\SlotsMachine;

class Buffalo extends SlotsMachine
{
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