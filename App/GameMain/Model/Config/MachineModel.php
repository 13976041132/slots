<?php
/**
 * 机台模块
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class MachineModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_machine', 'machineId');
    }

    public function getOne($machineId)
    {
        $key = 'Machine-' . $machineId;
        $where = array('machineId' => $machineId);

        return $this->fetchOneWithBuffer($key, $where);
    }

    public function getAll()
    {
        $where = array(
            'parentMachineId' => 0
        );

        $result = $this->fetchAll($where);

        return array_column($result, null, 'machineId');
    }
}