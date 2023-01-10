<?php

namespace FF\Machines;

use FF\Factory\Feature;
use FF\Machines\Features\BaseFeature;
use FF\Machines\SlotsModel\SlotsMachine;

class EEFortunes extends SlotsMachine
{
    public function onFeatureTriggered($featureId)
    {
        if ($this->getFeatureName($featureId) === FEATURE_PICK_GAME) {
            $this->getFeaturePlugin($featureId)->onTrigger();
        }
    }

    /**
     * @param $featureId
     * @return BaseFeature
     */
    public function getFeaturePlugin($featureId)
    {
        if ($this->getFeatureName($featureId) === FEATURE_PICK_GAME) {
            return Feature::jackpotPickGame($this, $featureId);
        }

        return parent::getFeaturePlugin($featureId);
    }

}