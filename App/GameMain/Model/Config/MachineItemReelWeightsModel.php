<?php
/**
 * FeatureGame模块
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class MachineItemReelWeightsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_machine_item_reel_weights');
    }

    public function getAll($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}