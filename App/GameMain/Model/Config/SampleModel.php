<?php
/**
 * ReelSampleModel
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class SampleModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_sample');
    }

    public function getSamples($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}