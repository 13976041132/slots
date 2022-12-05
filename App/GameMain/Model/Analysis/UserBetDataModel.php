<?php
/**
 * 用户下注额数据模型
 */

namespace FF\App\GameMain\Model\Analysis;

use FF\Extend\MyModel;

class UserBetDataModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ANALYSIS, 't_user_bet_data');
    }

    public function getCommonUsedBet($uid, $startDate, $minTimes = 150)
    {
        $where = array('uid' => $uid,);
        if ($startDate) {
            $where['date'] = array('>=', $startDate);
        }

        $fields = 'totalBet, SUM(betTimes) AS times';
        $orderBy = array('times' => 'desc');
        $groupBy = 'totalBet';
        $result = $this->fetchAll($where, $fields, $orderBy, $groupBy);

        if (!$result) return 0;

        $reachedBets = array();
        foreach ($result as $row) {
            if ($row['times'] >= $minTimes) {
                $reachedBets[] = $row['totalBet'];
            }
        }

        // 修改：150次以上，使用最多的bet
        if ($reachedBets) {
            return $reachedBets[0];
        } else {
            return 0;
        }
    }

    public function getActivityBet($uid, $startDate, $minTimes = 10)
    {
        $where = array('uid' => $uid,);
        if ($startDate) {
            $where['date'] = array('>=', $startDate);
        }

        $fields = 'totalBet, SUM(betTimes) AS times';
        $orderBy = array('totalBet' => 'desc');
        $groupBy = 'totalBet';
        $result = $this->fetchAll($where, $fields, $orderBy, $groupBy);

        if (!$result) return 0;

        $reachedBets = array();
        foreach ($result as $row) {
            if ($row['times'] >= $minTimes) {
                $reachedBets[] = $row['totalBet'];
            }
        }

        // 修改：10次以上，最大的bet
        if ($reachedBets) {
            return $reachedBets[0];
        } else {
            return 0;
        }
    }

    public function updateBetTimes($uid, $date, $machineId, $totalBet, $times)
    {
        $sets = "uid = {$uid}, date = '{$date}', machineId = {$machineId}, totalBet = {$totalBet}, betTimes = {$times}";

        $sql = "INSERT INTO {$this->table()} SET {$sets} ON DUPLICATE KEY UPDATE betTimes = betTimes + {$times}";

        return $this->db()->query($sql);
    }
}
