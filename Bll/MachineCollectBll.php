<?php
/**
 * 机台收集业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Library\Utils\Utils;

class MachineCollectBll
{
    /**
     * 获取机台收集进度信息
     */
    public function getCollectInfo($uid, $machineId, $gameInfo = null)
    {
        $collectNodes = Bll::machine()->getMachineCollect($machineId);
        if (!$collectNodes) {
            return array(
                'node' => 0,
                'unlockBet' => 0, 'inFreeSpin' => false,
                'collectType' => '', 'collectItems' => [],
                'progress' => 0, 'target' => 0, 'boosters' => '',
                'spinTimes' => 0, 'betSummary' => 0, 'avgBet' => 0, 'value' => 0,
                'complete' => false
            );
        }

        if (!$gameInfo) {
            $fields = 'totalBet,collectNode,collectTarget,collectProgress,collectSpinTimes,collectBetSummary,collectAvgBet,collectValue';
            $gameInfo = Bll::game()->getGameInfo($uid, $machineId, $fields);
        }

        $node = max(1, $gameInfo['collectNode']);
        $progress = $gameInfo['collectProgress'];
        $target = $gameInfo['collectTarget'];

        //容错
        if (!isset($collectNodes[$node])) {
            $node = 1;
            $progress = 0;
        }

        $nodeCfg = $collectNodes[$node];
        $target = $target ?: $this->getCollectTarget($uid, $machineId, $nodeCfg['target']);
        $complete = $progress >= $target;
        $avgBet = $gameInfo['collectAvgBet'] ?: $gameInfo['totalBet'];
        $boosters = [];

        return array(
            'node' => $node,
            'unlockBetLevel' => (int)$nodeCfg['activeBetLevel'],
            'inFreeSpin' => $nodeCfg['inFreeSpin'],
            'collectType' => $nodeCfg['collectType'],
            'collectItems' => $nodeCfg['collectItem'] ? explode(',', $nodeCfg['collectItem']) : [],
            'boosters' => $boosters ? json_encode($boosters) : '',
            'spinTimes' => $gameInfo['collectSpinTimes'],
            'betSummary' => $gameInfo['collectBetSummary'],
            'avgBet' => floor($avgBet / 1000) * 1000,
            'value' => $gameInfo['collectValue'],
            'progress' => min($progress, $target),
            'target' => $target,
            'complete' => $complete
        );
    }

    /**
     * 获取收集的 target
     */
    public function getCollectTarget($uid, $machineId, $target)
    {
        if (is_array($target)) {
            $limitType = $target['limitType'];
            if ($limitType === 'DoubleRandom') {
                $areaNum = Utils::randByRates($target['numRatios']);
                list($minNum, $maxNum) = explode('-', $areaNum);
                $target = mt_rand($minNum, $maxNum);
            }
        }

        // 更新收集目标值
        Bll::game()->updateGameInfo($uid, $machineId, array(
            'collectTarget' => $target
        ));

        return (int)$target;
    }
}