<?php
/**
 * 用户管理
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Model;

class UserController extends BaseController
{
    /**
     * 查询用户
     */
    public function index()
    {
        $uid = (int)$this->getParam('uid', false);

        if ($uid) {
            $userInfo = Model::user()->getOneById($uid);
        } else {
            $userInfo = null;
        }

        $data = array();
        $data['userInfo'] = $userInfo;

        $this->display('index.html', $data);
    }

    /**
     * 查看玩家余额波动
     */
    public function balances()
    {
        $uid = $this->getParam('uid');
        $page = $this->getParam('page', false, 1);
        $limit = $this->getParam('limit', false, 500);
        $table = $this->getParam('table', false);
        $start = $this->getParam('start', false);
        $end = $this->getParam('end', false);

        $where = array();
        $where['uid'] = $uid;
        if ($start) $where['w1'] = array('sql', "time >= '" . addslashes($start) . "'");
        if ($end) $where['w2'] = array('sql', "time <= '" . addslashes($end) . "'");
        $where['settled'] = 1;

        $data = Model::betLog($table)->getPageList($page, $limit, $where, 'id,machineId,betSeq,balance', 'time asc');

        $points = [];
        $plotLines = [];
        $curMachineId = '';
        $machines = Bll::machine()->getAllMachines();

        foreach ($data['list'] as $index => $row) {
            $machineId = $row['machineId'];
            $points[] = array(
                'y' => $row['balance'],
                'x' => $index,
                'id' => $row['id'],
                'betSeq' => $row['betSeq'],
                'machineName' => $machines[$machineId]['name'] ?? $machineId
            );
            if ($curMachineId != $machineId) {
                $curMachineId = $row['machineId'];
                $plotLines[] = array(
                    'color' => '#FF0000',
                    'width' => 2,
                    'value' => $index
                );
            }
        }

        $data['list'] = $points;
        $data['plotLines'] = $plotLines;

        //历史表
        $data['tables'] = Model::betLog()->getHistoryTables();

        $this->display('balances.html', $data);
    }
}
