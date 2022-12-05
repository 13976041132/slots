<?php
/**
 * PayLineModel
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class PayLineModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_payline');
    }

    public function getAllLine($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId), null, array('seq' => 'asc'));
    }
}