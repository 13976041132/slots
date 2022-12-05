<?php
/**
 * 机台收集业务逻辑（Feature收集）
 */

namespace FF\Bll;

use FF\Factory\Bll;

class MachineCollectGameBll
{
    /**
     * 获取机台收集进度信息
     */
    public function getCollectInfo($uid, $machineId, $gameInfo = null)
    {
        $featureGames = Bll::machine()->getFeatureGames($machineId);
        $featureGames = array_column($featureGames, null, 'featureName');
        $featureCfg = $featureGames[FEATURE_COLLECT_GAME] ?? [];

        if (!$featureCfg) {
            return array(
                'node' => 0, 'unlockBet' => 0, 'inFreeSpin' => false,
                'collectType' => '', 'collectItems' => [], 'progress' => 0,
                'target' => 0, 'boosters' => '', 'spinTimes' => 0, 'betSummary' => 0,
                'avgBet' => 0, 'value' => 0, 'complete' => false
            );
        }

        $triggerOptions = $featureCfg['triggerOptions'];
        $awardConfig = $featureCfg['itemAwardLimit']['nodesAward'];

        if (!$gameInfo) {
            $fields = 'totalBet,collectNode,collectTarget,collectProgress,collectSpinTimes,collectBetSummary,collectAvgBet,collectValue';
            $gameInfo = Bll::game()->getGameInfo($uid, $machineId, $fields);
        }

        $node = max(1, $gameInfo['collectNode']);
        $progress = $gameInfo['collectProgress'];
        $target = $gameInfo['collectTarget'];

        //容错
        if (!isset($awardConfig[$node])) {
            $node = 1;
            $progress = 0;
        }

        $target = $target ?: $this->getCollectTarget($uid, $machineId, $triggerOptions['targetNumber']);
        $complete = $progress >= $target;
        $avgBet = $gameInfo['collectAvgBet'] ?: $gameInfo['totalBet'];

        return array(
            'node' => $node,
            'unlockBetLevel' => (int)$triggerOptions['unlockBetLevel'],
            'inFreeSpin' => $triggerOptions['inFreeSpin'],
            'collectType' => 'MachineItem',
            'collectItems' => $triggerOptions['targetSymbol'] ? explode(',', $triggerOptions['targetSymbol']) : [],
            'boosters' => '',
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
        // 更新收集目标值
        Bll::game()->updateGameInfo($uid, $machineId, array(
            'collectTarget' => $target
        ));

        return (int)$target;
    }
}