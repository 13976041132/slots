<?php
/**
 * 下注日志模块
 */

namespace FF\App\GameMain\Model\Log;

use FF\Extend\MyModel;
use FF\Framework\Common\DBResult;

class BetLogModel extends MyModel
{
    public function __construct($testId = 0)
    {
        $table = 't_bet_log';
        if ($testId) $table .= '_test' . $testId;
        parent::__construct(DB_LOG, $table);
    }

    public function getHistoryTables()
    {
        $sql = "SHOW TABLES LIKE 't\_bet\_log\_test%'";
        $result = $this->db()->query($sql, null, DBResult::FETCH_ALL);
        $tables = array_map('current', $result);

        return array_reverse($tables);
    }

    public function generate($data)
    {
        $betContext = $data['betContext'];
        $prizes = $data['prizes'];

        return array(
            'betId' => $data['betId'],
            'uid' => $data['uid'],
            'machineId' => $data['machineId'],
            'betSeq' => $data['betSeq'],
            'isNoviceProtect' => isset($betContext['noviceProtect']) ? (int)$betContext['noviceProtect'] : 0,
            'isIntervene' => isset($betContext['isIntervene']) ? (int)$betContext['isIntervene'] : 0,
            'interveneType' => isset($betContext['interveneType']) ? $betContext['interveneType'] : '',
            'interveneNo' => isset($betContext['interveneNo']) ? $betContext['interveneNo'] : '',
            'cost' => $betContext['cost'],
            'balance' => $data['balance'],
            'betMultiple' => $betContext['betMultiple'],
            'totalBet' => $betContext['totalBet'],
            'betRatio' => $betContext['betRatio'],
            'isMaxBet' => (int)$betContext['isMaxBet'],
            'isFreeSpin' => (int)$betContext['isFreeSpin'],
            'isLastFreeSpin' => (int)$betContext['isLastFreeSpin'],
            'isReFreeSpin' => (int)$betContext['isReFreeSpin'],
            'spinTimes' => $betContext['spinTimes'],
            'stickyElements' => json_encode($betContext['stickyElements'] ?? []),
            'steps' => json_encode($data['steps']),
            'extra' => json_encode($data['extra']),
            'feature' => $betContext['feature'],
            'featureNo' => $betContext['featureNo'],
            'features' => $prizes['features'] ? implode(',', $prizes['features']) : '',
            'coinsAward' => $prizes['coins'],
            'freespinAward' => $prizes['freespin'],
            'multipleAward' => $prizes['multiple'],
            'totalWin' => $betContext['totalWin'],
            'jackpotWin' => $prizes['jackpotWin'],
            'settled' => $data['settled'] ? 1 : 0,
            'version' => $data['version'],
            'level' => $data['level'] ?: 1,
            'time' => $data['time'] ?: now(),
            'microtime' => $data['microtime'] ?: microtime(true) * 10000
        );
    }

    public function updateInfo($betId, $updates)
    {
        $where = array(
            'betId' => $betId
        );

        return $this->update($updates, $where);
    }

    /**
     * 查询相连的spin记录
     * 条件，id,uid,machineId
     *
     * 分开查询前后数据
     */
    public function getBetLogByAction($id, $action, $isTest = false)
    {
        // 查询ID对应的数据
        // $fields = 'id,uid,machineId,stickyElements,steps,feature,microtime';
        $fields = null;
        $betLog = $this->getOneById($id, $fields);
        if (!$betLog) return null;
        if (!$action) return $betLog;

        $where = [];
        $order = '';
        if ($action == 'prev') {
            if ($isTest) {
                $where['id'] = array('<', $id);
                $order = "id DESC";
            } else {
                $where['microtime'] = array('<', $betLog['microtime']);
                $order = "microtime DESC";
            }
        } else if ($action == 'next') {
            if ($isTest) {
                $where['id'] = array('>', $id);
                $order = "id ASC";
            } else {
                $where['microtime'] = array('>', $betLog['microtime']);
                $order = "microtime ASC";
            }
        }
        $where['uid'] = array('=', $betLog['uid']);
        $where['machineId'] = array('=', $betLog['machineId']);
        $result = $this->fetchAll($where, $fields, $order, '', 1);

        return $result[0] ?: $betLog;
    }

}