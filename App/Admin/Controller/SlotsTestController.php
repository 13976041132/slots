<?php
/**
 * 机台测试控制器
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Common\DBResult;
use FF\Framework\Core\FF;

class SlotsTestController extends BaseController
{
    public function init()
    {
        if (FF::isProduct()) {
            FF::throwException(Code::FAILED);
        }

        parent::init();
    }

    public function index()
    {
        $page = $this->getParam('page', false, 1);
        $limit = $this->getParam('limit', false, 10);

        $data['data'] = Model::slotsTest()->getPageList($page, $limit, null, null, array('testId' => 'desc'));
        foreach ($data['data']['list'] as &$v) {
            $v['progress'] = round($v['bettedTimes'] * 100 / $v['betTimes'], 1);
        }

        $this->display('index.html', $data);
    }

    public function edit()
    {
        $testId = $this->getParam('testId', false);
        $copy = $this->getParam('copy', false);

        $data = array();

        if ($testId) {
            $test = Bll::slotsTest()->getTestInfo($testId);
            if (!$copy && $test && $test['status'] != 0) {
                FF::throwException(Code::FAILED, '测试已启动或结束，不能进行编辑');
            }
            $formatFunc = function ($v) {
                return (int)$v;
            };
            $test['perBetTimes'] = json_encode(array(
                'game' => array_map($formatFunc, explode(',', $test['machineIds'])),
                'times' => array_map($formatFunc, explode(',', $test['perBetTimes'])),
            ));
            $data['test'] = $test;
        }

        $this->display('edit.html', $data);
    }

    public function create()
    {
        $data = array(
            'userLevel' => (int)$this->getParam('userLevel'),
            'totalBet' => (int)$this->getParam('totalBet'),
            'betGrade' => (int)$this->getParam('betGrade', false, 0),
            'betUsers' => (int)$this->getParam('betUsers'),
            'perBetTimes' => (string)$this->getParam('perBetTimes'),
            'initCoins' => (int)$this->getParam('initCoins', false, 0),
            'isNovice' => (string)$this->getParam('isNovice'),
            'betAutoRaise' => (int)$this->getParam('betAutoRaise'),
            'featureOpened' => (int)$this->getParam('featureOpened'),
            'ivOpened' => (int)$this->getParam('ivOpened'),
            'createTime' => now(),
            'status' => 0,
        );

        $result = $this->_create('slotsTest', $data);

        //清理可能存在的历史脏数据
        Bll::slotsTest()->clearTestCache($result['id'], $data['machineIds']);

        if ($this->getParam('run')) {
            Bll::slotsTest()->start($result['id']);
            $result['message'] = '测试已启动';
        }

        return $result;
    }

    public function update()
    {
        $testId = $this->getParam('testId');

        $data = array(
            'userLevel' => (int)$this->getParam('userLevel'),
            'totalBet' => (int)$this->getParam('totalBet'),
            'betGrade' => (int)$this->getParam('betGrade', false, 0),
            'betUsers' => (int)$this->getParam('betUsers'),
            'perBetTimes' => (string)$this->getParam('perBetTimes'),
            'initCoins' => (int)$this->getParam('initCoins', false, 0),
            'isNovice' => (string)$this->getParam('isNovice'),
            'betAutoRaise' => (int)$this->getParam('betAutoRaise'),
            'featureOpened' => (int)$this->getParam('featureOpened'),
            'ivOpened' => (int)$this->getParam('ivOpened'),
        );

        $result = $this->_update('slotsTest', $testId, $data);

        if ($this->getParam('run')) {
            Bll::slotsTest()->start($testId);
            $result['message'] = '测试已启动';
        }

        return $result;
    }

    public function delete()
    {
        $testId = $this->getParam('id');

        $result = $this->_delete('slotsTest', $testId, $testInfo);

        Bll::slotsTest()->onDeleted($testId, $testInfo);

        return $result;
    }

    public function deleteMulti()
    {
        $ids = $this->getParam('ids');

        if (!$ids || !is_array($ids)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        $tests = Model::slotsTest()->getMulti($ids, 'testId,machineIds,status,logPath');
        if (!$tests) {
            FF::throwException(Code::FAILED, '测试记录不存在');
        }

        foreach ($tests as $test) {
            if ($test['status'] == 1) {
                FF::throwException(Code::FAILED, '有测试正在运行中，不能进行删除');
            }
        }

        Model::slotsTest()->deleteMulti(array_keys($tests));

        foreach ($tests as $testId => $testInfo) {
            Bll::slotsTest()->onDeleted($testId, $testInfo);
        }

        return array(
            'message' => '已删除',
            'reload' => true
        );
    }

    protected function checkData($modelName, &$data, $curData, $action)
    {
        if ($action == 'update' && $curData['status'] != 0) {
            FF::throwException(Code::FAILED, '测试已启动或结束，不能进行编辑');
        }
        if ($action == 'delete' && $curData['status'] == 1) {
            FF::throwException(Code::FAILED, '测试正在运行中，不能进行删除');
        }

        if ($action == 'create' || $action == 'update') {
            if ($data['betUsers'] <= 0) {
                FF::throwException(Code::PARAMS_INVALID, '下注人数无效');
            }
            if ($data['totalBet'] <= 0) {
                FF::throwException(Code::PARAMS_INVALID, '下注额无效');
            }
            if ($data['betGrade'] < 0) {
                FF::throwException(Code::PARAMS_INVALID, '下注档位无效');
            }
            if ($data['userLevel'] <= 0) {
                FF::throwException(Code::PARAMS_INVALID, '初始等级无效');
            }
            if ($data['initCoins'] < 0) {
                FF::throwException(Code::PARAMS_INVALID, '初始金币数无效');
            }
            $perBetTimes = json_decode($data['perBetTimes'], true);
            if (!$perBetTimes || !is_array($perBetTimes) || empty($perBetTimes['game']) || empty($perBetTimes['times'])) {
                FF::throwException(Code::PARAMS_INVALID, '每人下注次数无效');
            }
            if (!is_array($perBetTimes['game']) || !is_array($perBetTimes['times'])) {
                FF::throwException(Code::PARAMS_INVALID, '每人下注次数无效');
            }
            if (count($perBetTimes['game']) != count($perBetTimes['times'])) {
                FF::throwException(Code::PARAMS_INVALID, '每人下注次数无效');
            }
            foreach (['game', 'times'] as $key) {
                $perBetTimes[$key] = array_values($perBetTimes[$key]);
                foreach ($perBetTimes[$key] as $v) {
                    if (!is_int($v) || $v <= 0) {
                        FF::throwException(Code::PARAMS_INVALID, '每人下注次数无效');
                    }
                }
            }
            $betTimes = $data['betUsers'] * array_sum($perBetTimes['times']);
            if ($betTimes > 10000000) {
                FF::throwException(Code::PARAMS_INVALID, '下注总次数过大(超过了1000万)');
            }
            $data['betTimes'] = $betTimes;
            $data['machineId'] = $perBetTimes['game'][0];
            $data['machineIds'] = implode(',', $perBetTimes['game']);
            $data['perBetTimes'] = implode(',', $perBetTimes['times']);
        }

        if ($action == 'create') {
            $result = Model::slotsTest()->fetchOne(null, 'count(1) as count');
            if ($result['count'] >= 10) {
                FF::throwException(Code::FAILED, '测试记录太多（最多10个），请先删除部分历史测试记录');
            }
        }
    }

    public function setStatus()
    {
        $testId = (int)$this->getParam('testId');
        $status = (int)$this->getParam('status');

        if (!in_array($status, [1, 2], true)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        $test = Bll::slotsTest()->getTestInfo($testId);

        if ($status == 1 && $test['status'] == 0) {
            $result = Bll::slotsTest()->start($testId);
        } elseif ($status == 2 && $test['status'] == 1) {
            $result = Bll::slotsTest()->stop($testId);
        } else {
            $result = false;
        }

        if (!$result) {
            FF::throwException(Code::FAILED, '操作失败');
        }

        return array(
            'message' => $status == 1 ? '测试已启动' : '测试已终止',
            'reload' => true
        );
    }

    public function analysis()
    {
        $testId = (int)$this->getParam('testId');

        $test = Bll::slotsTest()->getTestInfo($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID, '该测试不存在');
        if ($test['status'] != 2) FF::throwException(Code::FAILED, '测试未完成，无法分析');

        $test['machineIds'] = array_unique(explode(',', $test['machineIds']));
        $test['progress'] = round($test['bettedTimes'] * 100 / $test['betTimes'], 1);
        $test['testers'] = $test['testers'] ? json_decode($test['testers'], true) : array();
        $test['stats'] = $test['stats'] ? json_decode($test['stats'], true) : array();

        $data['machines'] = Bll::machine()->getAllMachines();
        $data['test'] = $test;

        $this->display('analysis.html', $data);
    }

    public function getMachineInfo()
    {
        $machineId = $this->getParam('machineId');
        $userLevel = $this->getParam('userLevel');

        $betOptions = Bll::machineBet()->getUnlockedBets(0, $machineId, '', $userLevel);

        $data['betOptions'] = $betOptions;

        return $data;
    }

    public function getProgress()
    {
        $testId = $this->getParam('testId');

        $test = Bll::slotsTest()->getTestInfo($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID, '该测试不存在');

        $progress = round($test['bettedTimes'] * 100 / $test['betTimes'], 1);

        return array(
            'status' => (int)$test['status'],
            'bettedTimes' => (int)$test['bettedTimes'],
            'progress' => $progress,
        );
    }

    public function getBalances()
    {
        $testId = $this->getParam('testId');
        $uid = $this->getParam('uid');

        $test = Bll::slotsTest()->getTestInfo($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID, '该测试不存在');

        $where = array();
        $where['uid'] = $uid;
        $where['settled'] = 1;
        $result = Model::betLog($testId)->fetchAll($where, 'balance', array('id' => 'asc'));

        return array_column($result, 'balance');
    }

    public function getBalanceDis()
    {
        $testId = $this->getParam('testId');
        $type = $this->getParam('type');

        $test = Bll::slotsTest()->getTestInfo($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID, '该测试不存在');

        if (!$test['initCoins']) {
            FF::throwException(Code::FAILED, '本次测试未设置初始金币，不提供资产余额分析');
        }

        $where = array();
        $model = Model::betLog($testId);
        $table = $model->table();

        if ($type == 1 || $type == 2) {
            if ($type == 1) { //新手期结束后的玩家资产分布
                $where['id'] = array('in', "SELECT max(id) FROM {$table} WHERE sampleTag = 'novice' AND settled = 1 GROUP BY uid");
            } elseif ($type == 2) { //整个Spin结束后的玩家资产分布
                $where['id'] = array('in', "SELECT max(id) FROM {$table} WHERE settled = 1 GROUP BY uid");
            }
            $result = $model->fetchAll($where, 'balance');
        } elseif ($type == 3) { //整个新手期间玩家最大资产倍数分布
            $where['sampleTag'] = 'novice';
            $result = $model->fetchAll($where, "MAX(balance) AS balance", null, 'uid');
        } else {
            $result = array();
        }

        $counts = array();
        $totalCount = count($result);
        foreach ($result as $row) {
            $v = $row['balance'];
            if ($type == 1 || $type == 2) {
                $v = (string)(ceil($row['balance'] / 5000000) * 5000000);
            } elseif ($type == 3) {
                $v = (string)round($row['balance'] / $test['initCoins'], 1);
            }
            $counts[$v]++;
        }
        ksort($counts, SORT_NUMERIC);

        $data = array();
        $data['keys'] = array();
        $data['values'] = array();
        foreach ($counts as $v => $count) {
            $data['keys'][] = $type == 1 || $type == 2 ? number_format($v) : $v;
            $data['values'][] = round($count * 100 / $totalCount, 2);
        }

        return $data;
    }

    public function getSpinTimesDis()
    {
        $testId = $this->getParam('testId');
        $type = $this->getParam('type');

        $test = Bll::slotsTest()->getTestInfo($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID, '该测试不存在');

        $t_bet_log = Model::betLog($testId)->table();

        if ($type == 1) { //破产次数
            $subQuery = "SELECT uid, COUNT(id) AS times FROM {$t_bet_log} WHERE balance < totalBet AND settled = 1 GROUP BY uid";
        } else { //新手期内资产余额达到顶峰时的spin次数
            $maxBalanceQuery = "SELECT uid, MAX(balance) AS maxBalance FROM {$t_bet_log} WHERE sampleTag = 'novice' AND settled = 1 GROUP BY uid";
            $maxBalanceRowsQuery = "SELECT a.id, a.uid, a.balance FROM {$t_bet_log} AS a, ({$maxBalanceQuery}) AS b WHERE a.uid = b.uid AND a.balance = b.maxBalance AND a.sampleTag = 'novice' AND a.settled = 1";
            $maxBalanceFirstQuery = "SELECT uid, MIN(id) AS id FROM ({$maxBalanceRowsQuery}) AS t1 GROUP BY uid";
            $subQuery = "SELECT x.uid, COUNT(x.id) AS times FROM {$t_bet_log} AS x, ({$maxBalanceFirstQuery}) AS y WHERE x.id <= y.id AND x.uid = y.uid AND x.settled = 1 GROUP BY x.uid";
        }

        //次数分布
        $sql = "SELECT times, COUNT(1) as count FROM ({$subQuery}) AS t2 GROUP BY times";
        $result = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ALL);

        //下注总人数
        $sql = "SELECT COUNT(DISTINCT uid) AS `count` FROM {$t_bet_log}";
        $userCount = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ONE)['count'];

        $counts = array();
        if ($type == 1) {
            $counts = array_column($result, 'count', 'times');
        } else {
            foreach ($result as $row) { //每50次为一个区间
                $v = ceil($row['times'] / 50) * 50;
                $counts[$v] += $row['count'];
            }
        }
        ksort($counts);

        $data = array();
        $data['keys'] = array();
        $data['values'] = array();
        foreach ($counts as $v => $count) {
            $data['keys'][] = $type == 1 ? $v : (($v - 50) . '-' . $v);
            $data['values'][] = round($count * 100 / $userCount, 3);
        }

        return $data;
    }

    public function getWinMultiple()
    {
        $testId = $this->getParam('testId');
        $type = $this->getParam('type', false);
        $limitRect = $this->getParam('rect', false);

        $data = Bll::slotsTest()->getWinMultiples($testId, $type, $limitRect);

        return array(
            'keys' => array_keys($data),
            'values' => array_values($data),
        );
    }

    public function getWinPopups()
    {
        $testId = $this->getParam('testId');

        $data = Bll::slotsTest()->getWinPopups($testId);

        return $data;
    }
}