<?php
/**
 * 各个机台独立
 * 新机台体验干预逻辑
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class ExperienceEventBll extends InterveneBll
{
    protected $type = 'Experience';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $group = 'Default';
        $machineId = $userInfo['machineId'];
        $spinTimes = $userInfo['spinTimes'];
        $interveneCfgs = Config::get('machine/intervene-experience', "{$machineId}/{$group}", false);
        if (!$interveneCfgs || !isset($interveneCfgs[$spinTimes])) {
            return $result;
        }

        $interveneCfg = $interveneCfgs[$spinTimes];
        if (!Utils::isHitByRate($interveneCfg['hitRatio'])) {
            return $result;
        }

        //判断玩家feature历史触发次数是否满足干预条件
        $hitFeature = $interveneCfg['hitFeature'];
        $hitCondition = $interveneCfg['hitCondition'];
        $hitTimes = $userInfo['featureTimes'][$hitFeature] ?? 0;
        if (isset($hitCondition['featureTimes'])) {
            if (!Utils::isValueMatched($hitTimes, $hitCondition['featureTimes'])) {
                return $result;
            }
        }

        $result['isIntervene'] = true;
        $result['interveneType'] = 'feature';
        $result['interveneValue'] = $hitFeature;
        return $result;
    }
}