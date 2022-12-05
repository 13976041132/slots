<?php
/**
 * 付费干预业务逻辑
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class RechargeEventBll extends InterveneBll
{
    protected $type = 'Recharge';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $machineId = $userInfo['machineId'];
        $interveneCfg = Config::get('machine/intervene-recharge', $machineId, false);
        if (!$interveneCfg) return $result;

        if (!$userInfo['rechargePlayNum'] || $userInfo['rechargePlayNum'] < $userInfo['lastRechargeFeatureNum']) {
            return $result;
        }

        $result['isIntervene'] = true;
        $result['interveneType'] = 'Feature';
        $result['interveneValue'] = Utils::randByRates($interveneCfg['winFeature']);

        return $result;
    }
}