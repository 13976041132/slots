<?php
/**
 * 游戏信息模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class GameInfoModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_game_info');
    }

    public function init($uid, $machineId, $betMultiple, $totalBet)
    {
        $data = array(
            'uid' => $uid,
            'machineId' => $machineId,
            'betMultiple' => $betMultiple,
            'totalBet' => $totalBet,
        );

        return $this->insert($data);
    }

    public function getInfo($uid, $machineId, $fields = '*')
    {
        $where = array(
            'uid' => $uid,
            'machineId' => $machineId,
        );

        return $this->fetchOne($where, $fields);
    }

    public function updateInfo($uid, $machineId, $data, $where = null)
    {
        $_where = array(
            'uid' => $uid,
            'machineId' => $machineId,
        );

        if ($where) {
            $where = array_merge($_where, $where);
        } else {
            $where = $_where;
        }

        return $this->update($data, $where);
    }

    public function updateAllMachine($uid, $data, $where = null)
    {
        $_where = array(
            'uid' => $uid
        );

        if ($where) {
            $where = array_merge($_where, $where);
        } else {
            $where = $_where;
        }

        return $this->update($data, $where, 0);
    }

    public function getSpinTimes($uid)
    {
        $result = $this->fetchAll(array('uid' => $uid), 'machineId,spinTimes');

        $data = array();
        foreach ($result as $row) {
            $data[$row['machineId']] += $row['spinTimes'];
        }

        return $data;
    }
}