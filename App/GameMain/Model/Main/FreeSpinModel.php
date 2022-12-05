<?php
/**
 * 免费Spin模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class FreeSpinModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_freespin');
    }

    public function getInfo($uid, $machineId)
    {
        $where = array(
            'uid' => $uid,
            'machineId' => $machineId,
        );

        return $this->fetchOne($where);
    }

    public function init($uid, $machineId, $times)
    {
        $data = array(
            'uid' => $uid,
            'machineId' => $machineId,
            'initTimes' => $times,
            'totalTimes' => $times,
            'spinTimes' => 0,
        );

        return $this->insert($data);
    }

    public function addTimes($uid, $machineId, $count)
    {
        if ($count <= 0) return 0;

        $updates = array(
            'totalTimes' => array('+=', $count),
        );

        $where = array(
            'uid' => $uid,
            'machineId' => $machineId,
        );

        return $this->update($updates, $where);
    }

    public function incSpinTimes($uid, $machineId, $currTimes)
    {
        $updates = array(
            'spinTimes' => $currTimes + 1
        );

        $where = array(
            'uid' => $uid,
            'machineId' => $machineId,
            'spinTimes' => $currTimes
        );

        return $this->update($updates, $where);
    }

    public function clear($uid, $machineId)
    {
        $where = array(
            'uid' => $uid,
            'machineId' => $machineId,
        );

        return $this->delete($where);
    }
}