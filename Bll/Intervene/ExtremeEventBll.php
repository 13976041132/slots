<?php
/**
 * 极端体验干预逻辑
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class ExtremeEventBll extends InterveneBll
{
    protected $type = 'Extreme';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $machineId = $userInfo['machineId'];
        $interveneCfg = Config::get('machine/intervene-extreme', $machineId, false);
        if (!$interveneCfg) return $result;

        if ($userInfo['isRelativeBankrupt'] == 'Y') {
            $maxNoBigWinTimes = reset($interveneCfg['maxNoBigWin']);
            $maxNoFeature = reset($interveneCfg['maxNoFeature']);
            $maxNoWin = reset($interveneCfg['maxNoWin']);
        } else {
            $maxNoBigWinTimes = end($interveneCfg['maxNoBigWin']);
            $maxNoFeature = end($interveneCfg['maxNoFeature']);
            $maxNoWin = end($interveneCfg['maxNoWin']);
        }

        //连续多局不中feature，则进行干预，使之必中feature
        if ($userInfo['notFeatureTimes'] >= $maxNoFeature) {
            $feature = Utils::randByRates($interveneCfg['winFeature']);
            $result['isIntervene'] = true;
            $result['interveneType'] = 'feature';
            $result['interveneValue'] = $feature;
            $result['interveneFlag'] = 'Extreme3';
            return $result;
        }

        if ($userInfo['notBigWinTimes'] >= $maxNoBigWinTimes
            && $userInfo['balance'] < $interveneCfg['maxBalance']
            && $userInfo['maxBetRatio'] < $interveneCfg['maxBetRatio']) {
            $result['isIntervene'] = true;
            $result['interveneType'] = 'winMulti';
            $result['interveneValue'] = rand($interveneCfg['bigWinMultiple'][0], $interveneCfg['bigWinMultiple'][1]);
            $result['interveneElements'] = explode('/', $interveneCfg['randomElements'] ?? '');
            $result['interveneFlag'] = 'Extreme2';

            return $result;
        }

        //连续多局不中奖，则进行干预，使之必中奖
        if ($userInfo['notHitTimes'] >= $maxNoWin) {
            $result['isIntervene'] = true;
            $result['interveneType'] = 'hit';
            $result['interveneValue'] = true;
            $result['interveneFlag'] = 'Extreme1';
            return $result;
        }

        return $result;
    }
}