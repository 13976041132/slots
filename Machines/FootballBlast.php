<?php

namespace FF\Machines;

use FF\Machines\SlotsModel\LightningMachine;

class FootballBlast extends LightningMachine
{
    public function isLightning($featureId)
    {
        return $this->getFeatureName($featureId) === FEATURE_HOLD_AND_SPIN;
    }

}