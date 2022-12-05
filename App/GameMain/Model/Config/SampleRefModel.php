<?php
/**
 * SampleRefModel
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class SampleRefModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_sample_ref');
    }

    public function getAll($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }
}