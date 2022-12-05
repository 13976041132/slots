<?php
/**
 * Freespin业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Model;

class FreespinBll
{
    public function getInitInfo($times = 0)
    {
        return array(
            'initTimes' => $times, 'totalTimes' => $times, 'spinTimes' => 0
        );
    }

    //获取玩家freespin信息
    public function getFreespinInfo($uid, $machineId)
    {
        $info = Model::freespin()->getInfo($uid, $machineId);

        if (!$info) {
            $info = $this->getInitInfo();
        }

        return $info;
    }

    //初始化freespin
    public function init($uid, $machineId, $times)
    {
        $this->clearFreespin($uid, $machineId);

        Model::freespin()->init($uid, $machineId, $times);
    }

    //删除freespin信息
    public function clearFreespin($uid, $machineId)
    {
        Model::freespin()->clear($uid, $machineId);
    }

    //增加freespin次数
    public function addTimes($uid, $machineId, $count)
    {
        Model::freespin()->addTimes($uid, $machineId, $count);
    }

    //增加已进行freespin次数
    public function incSpinTimes($uid, $machineId, $currTimes)
    {
        return Model::freespin()->incSpinTimes($uid, $machineId, $currTimes);
    }
}