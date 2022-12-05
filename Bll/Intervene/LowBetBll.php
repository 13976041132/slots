<?php
/**
 * LowBet干预逻辑
 */

namespace FF\Bll\Intervene;

use FF\Library\Utils\Utils;

class LowBetBll extends InterveneBll
{
    protected $type = 'LowBet';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        $balance = $userInfo['balance'];
        $currentBet = $userInfo['currentBet'];
        $suggestBet = $userInfo['suggestBet'];
        $betRatio = $balance / $currentBet;

        $trigCondition = $this->getTrigCondition();
        if (!$trigCondition) return false;

        if ($currentBet >= $suggestBet) {
            return false;
        }
        if (!Utils::isValueMatched($balance, $trigCondition['coins'])) {
            return false;
        }
        if (!Utils::isValueMatched($betRatio, $trigCondition['currentBetRatio'])) {
            return false;
        }

        $times = $this->addDailyInterveneTimes($uid);
        if ($times > $this->getBaseInterveneCfg('trigTimes')) {
            return false;
        }

        $trigRatio = $this->getBaseInterveneCfg('trigProbability');
        $result = Utils::isHitByRate($trigRatio) ? 'Y' : 'N';

        return $result;
    }
}