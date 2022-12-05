<?php
/**
 * 测试模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class SlotsTestModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_TEST, 't_slots_test', 'testId');
    }

    public function setStarted($testId)
    {
        $update = array(
            'status' => 1,
            'startTime' => now()
        );

        $where = array(
            'testId' => $testId,
            'status' => array('in', [0, 3])
        );

        return $this->update($update, $where);
    }

    public function setEnded($testId, $error = '')
    {
        $update = array(
            'status' => 2,
            'endTime' => now(),
            'error' => $error
        );

        $where = array(
            'testId' => $testId,
            'status' => 1
        );

        return $this->update($update, $where);
    }

    public function setWaiting($testId)
    {
        $update = array(
            'status' => 3,
        );

        $where = array(
            'testId' => $testId,
            'status' => 0
        );

        return $this->update($update, $where);
    }

    public function getWaitingOne()
    {
        $where = array(
            'status' => 3
        );

        return $this->fetchOne($where);
    }

    public function updateBettedTimes($testId, $count = 1)
    {
        $update = array(
            'bettedTimes' => array('+=', $count)
        );

        $where = array(
            'testId' => $testId,
            'status' => 1,
        );

        return $this->update($update, $where);
    }

    public function updateBettedUsers($testId, $count = 1)
    {
        $update = array(
            'bettedUsers' => array('+=', $count)
        );

        $where = array(
            'testId' => $testId,
            'status' => 1,
        );

        return $this->update($update, $where);
    }

    public function setStatInfo($testId, $stats)
    {
        $update = array(
            'stats' => json_encode($stats)
        );

        $where = array(
            'testId' => $testId
        );

        return $this->update($update, $where);
    }
}