<?php
/**
 * 新手干预事件逻辑
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class NoviceEventBll extends InterveneBll
{
    protected $type = 'Novice';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $machineId = $userInfo['machineId'];
        $spinTimes = $userInfo['spinTimes'];
        $group = 'Default';

        $interveneCfgs = Config::get('machine/intervene-novice', "{$machineId}/{$group}", false);
        if (!$interveneCfgs) {
            return $result;
        }

        // 退出干预
        if ($spinTimes > max(array_keys($interveneCfgs))) {
            return $result;
        }

        $interveneCfg = $interveneCfgs[$spinTimes] ?: array();
        if (!$interveneCfg) {
            return $result;
        }

        if ($interveneCfg['hitEvent'] == 1 || $interveneCfg['hitEvent'] == 2) {
            if (!Utils::isHitByRate($interveneCfg['hitRatio'])) {
                return $result;
            }
            $hitCondition = $interveneCfg['hitCondition'];
            $regCoins = $userInfo['regCoins'];

            //判断玩家当前资产的增长幅度是否满足干预条件
            if (isset($hitCondition['coins']) && $regCoins) {
                if (Utils::isValueMatched($userInfo['balance'] / $regCoins, $hitCondition['coins'])) {
                    if ($interveneCfg['hitEvent'] == 1) {
                        $result['isIntervene'] = true;
                        $result['interveneType'] = 'Feature';
                        $result['interveneValue'] = $interveneCfg['hitFeature'];
                        return $result;
                    } elseif ($interveneCfg['hitEvent'] == 2) {
                        $result['isIntervene'] = true;
                        $result['interveneType'] = 'Sample';
                        $result['interveneValue'] = $interveneCfg['sampleGroup'];
                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    public function checkInterveneExit($uid, $interveneInfo, $userInfo)
    {
        $machineId = $userInfo['machineId'];
        $spinTimes = $userInfo['spinTimes'];
        $interveneCfgs = Config::get('machine/intervene-novice', "{$machineId}/Default", false);
        if (!$interveneCfgs) {
            return true;
        }

        if ($spinTimes >= max(array_keys($interveneCfgs))) {
            return true;
        }

        return false;
    }
}