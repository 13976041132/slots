<?php
/**
 * ReelItemsModel
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class SampleItemsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_sample_items');
    }

    public function getItems($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}