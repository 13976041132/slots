<?php
/**
 * 新手干预
 */

namespace FF\Bll\Intervene;

use FF\Library\Utils\Utils;

class NoviceBll extends InterveneBll
{
    protected $type = 'Novice';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $interveneCfg = $this->getBaseInterveneCfg();
        if (!$interveneCfg) return $result;

        // 不满足条件
        $spinTimes = $userInfo['spinTimes'];
        if (!Utils::isValueMatched($spinTimes, $interveneCfg['trigItemNum'])) return $result;

        $result['isIntervene'] = true;
        $result['interveneType'] = $interveneCfg['trigOptions']['trigType'];
        $result['interveneValue'] = $interveneCfg['trigOptions']['Sample_Group'];
        return $result;
    }

    public function checkInterveneExit($uid, $interveneInfo, $userInfo)
    {
        $interveneCfg = $this->getBaseInterveneCfg();
        if (!$interveneCfg) return true;


        $spinTimes = $userInfo['spinTimes'];
        if (!Utils::isValueMatched($spinTimes, $interveneCfg['trigItemNum'])) return true;
        if (Utils::isValueMatched($spinTimes, $interveneCfg['endCondition']['spinTimes'])) return true;

        return false;
    }
}