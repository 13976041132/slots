<?php
/**
 * 机台元素模块
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class MachineItemModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_machine_item');
    }

    public function getItems($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}