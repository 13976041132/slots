<?php
/**
 * FeatureGame模块
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class FeatureGameModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_feature_game');
    }

    public function getAll($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}