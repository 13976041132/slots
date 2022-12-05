<?php
/**
 * 下注日志业务逻辑层
 */

namespace FF\Bll;

use FF\Factory\Bll;

class BetLogBll
{
    /**
     * 当feature结束时，更新下注记录
     */
    public function onFeatureEnd($uid, $betId, $coinsWin, $totalWin, $settled, $balance)
    {
        Bll::asyncTask()->addTask(EVENT_SETTLEMENT, array(
            'betId' => $betId,
            'coinsAward' => $coinsWin,
            'totalWin' => $totalWin,
            'settled' => $settled ? 1 : 0,
            'balance' => $balance,
            'uid' => $uid
        ));
    }
}