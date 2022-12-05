<?php
/**
 * Feature选择器
 */

namespace FF\Machines\Features;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class Chooser extends BaseFeature
{
    public function onChoose($choosed)
    {
        $_this = $this->machineObj;
        $triggerOptions = $_this->getTriggerOptions($this->featureId);

        if (empty($triggerOptions['featureId'][$choosed - 1])) {
            FF::throwException(Code::FAILED);
        }

        $featureId = $triggerOptions['featureId'][$choosed - 1];

        $this->onEnd(0, $featureId);

        return array(
            'featureId' => $featureId,
        );
    }

    public function autoChoose(&$featureDetail = null)
    {
        $triggerOptions = $this->machineObj->getTriggerOptions($this->featureId);

        if (empty($triggerOptions['featureId'])) {
            FF::throwException(Code::FAILED);
        }

        $choosed = mt_rand(1, count($triggerOptions['featureId']));

        $featureId = $triggerOptions['featureId'][$choosed - 1];

        return array(
            'featureId' => $featureId,
        );
    }
}