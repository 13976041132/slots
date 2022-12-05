<?php
/**
 * PayTableModel
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;

class PayTableModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_paytable');
    }

    public function getAllResult($machineId)
    {
        return $this->fetchAll(array('machineId' => $machineId));
    }

    public function resetRealWeight($machineId)
    {
        $sql = "UPDATE {$this->table()} SET realWeight = weight WHERE machineId = '{$machineId}'";

        return $this->db()->query($sql);
    }

    public function decRealWeight($machineId, $resultId, $count)
    {
        $update = array(
            'realWeight' => array('-=', $count)
        );

        $where = array(
            'machineId' => $machineId,
            'resultId' => $resultId
        );

        return $this->update($update, $where);
    }
}