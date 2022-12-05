<?php
/**
 * 机台干预业务逻辑
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class BankruptEventBll extends InterveneBll
{
    protected $type = 'Bankrupt';

    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $interveneCfg = Config::get('machine/intervene-bankrupt', $userInfo['machineId'], false);
        $interveneInfo = $userInfo['interveneInfo'];
        $expireTime = ($interveneInfo['iHitBankruptcyTime'] ?? 0) + ($interveneInfo['iCurHitCD'] ?? 0) * 3600;

        if ($expireTime <= time() && $userInfo['betRatio'] == $interveneCfg['judgeLeft']) {
            // 无匹配用户
            if (!($userCfg = $this->getBankruptUserConfig($userInfo))) return $result;

            $registerDays = (time() - $userInfo['registerTime']) / 86400 + 1;
            $hitType = 0;

            //hitType的值,1:第一次破产干预， 2:注册七天前破产干预  3: 七天后破产干预
            if ($isIntervene = Utils::isHitByRate($userCfg['hitRatio'])) {
                $hitType = !$userInfo['isBankrupt'] ? 1 : ($registerDays > 7 ? 3 : 2);
            }
            $lastHitType = $interveneInfo['lastHitType'] ?? 0;
            if (!empty($interveneInfo['hitType'])) {
                $lastHitType = $interveneInfo['hitType'];
            }

            // 设置干预信息
            $result['interveneInfo'] = array(
                'iCurHitCD' => (int)($isIntervene ? $userCfg['hitCD'] : $userCfg['noHitCD']),
                'iHitBankruptcyCoin' => (int)$userInfo['balance'],
                'iHitBankruptcyBet' => (int)$userInfo['totalBet'],
                'iHitBankruptcyDays' => (int)$userInfo['loginDays'],
                'iHitBankruptcyTime' => time(),
                'iHitBankruptcyWinMultiple' => (int)($userInfo['winMultiples'][$userCfg['winType'] - 1] ?? 0),
                'iHitBankruptcyRate' => 0,
                'iHitInterveneTimes' => 0,
                'isFirstBankruptHit' => empty($userInfo['isBankrupt']),
                'iLastHitCD' => (int)($interveneInfo['iCurHitCD'] ?? 0),
                'lastHitType' => (int)$lastHitType,
                'hitType' => $hitType,
            );

            return $result;
        }

        if (empty($interveneInfo['hitType'])) {
            return $result;
        }

        if ($this->checkInterveneExit($uid, $interveneInfo, $userInfo)) {
            return $result;
        }

        $betRatio = $userInfo['betRatio'];
        if (!empty($interveneInfo['iHitInterveneTimes']) && $betRatio > 0) {
            return $result;
        }
        //判断是否要进行干预
        if (empty($interveneInfo['iHitInterveneTimes']) && !Utils::isHitByRate($betRatio > 1 ? 0.5 : 1)) {
            return $result;
        }

        if ($userInfo['betIndex'] - $interveneInfo['betIndex'] <= -$interveneCfg['betReduce']) {
            $winMulIndex = array_search($interveneInfo['iHitBankruptcyWinMultiple'], $userInfo['winMultiples']);
            if (isset($userInfo['winMultiples'][$winMulIndex + 1])) {
                $result['interveneInfo']['iHitBankruptcyWinMultiple'] = $userInfo['winMultiples'][$winMulIndex + 1];
            }
        }

        // 进行破产干预
        $result['isIntervene'] = true;
        $result['interveneType'] = 'feature';
        $result['interveneValue'] = Utils::randByRates($interveneCfg['winFeature']);

        return $result;
    }

    /**
     * 检查退出干预
     */
    public function checkInterveneExit($uid, $interveneInfo, $userInfo)
    {
        $result = parent::checkInterveneExit($uid, $interveneInfo, $userInfo);
        $interveneCfg = Config::get('machine/intervene-bankrupt', $userInfo['machineId'], false);
        //时间过期退出
        $expireTime = ($interveneInfo['iHitBankruptcyTime'] ?? 0) + ($interveneInfo['iCurHitCD'] ?? 0) * 3600;
        if ($expireTime < time()) {
            return true;
        }
        // 检查是否退出，是否超过提升的档位
        if ($userInfo['betIndex'] - ($interveneInfo['betIndex'] ?? 0) >= $interveneCfg['betRaise']) {
            return true;
        }
        // 检查退出条件，资产余额达到金币不足时的一定倍数
        if (!empty($interveneInfo['iHitBankruptcyCoin'])) {
            if ($userInfo['balance'] / $interveneInfo['iHitBankruptcyCoin'] >= $interveneCfg['coinMultiple']) {
                return true;
            }
        }
        // 检查是否退出,触发次数 >=2
        if (!empty($interveneInfo['iHitInterveneTimes']) && $interveneInfo['iHitInterveneTimes'] >= 2) {
            return true;
        }
        // 检查是否退出,中奖倍数满足条件
        if (!empty($interveneInfo['iHitBankruptcyWinMultiple']) &&
            $interveneInfo['iHitBankruptcyWinMultiple'] < $interveneInfo['iHitBankruptcyRate']) {
            return true;
        }

        return $result;
    }

    public function getBankruptUserConfig($userInfo)
    {
        $bankruptUserAllCfg = Config::get('machine/bankrupt-user');
        $bankruptUserCfg = $bankruptUserAllCfg[0] ?? array_shift($bankruptUserAllCfg);

        // 注册天数
        $registerDays = (time() - $userInfo['registerTime']) / 86400 + 1;
        // 匹配用户的对应属性
        $userCfg = [];
        foreach ($bankruptUserCfg as $userGroup => $bankruptCfg) {
            // 是否破产
            if ($bankruptCfg['isBankrupt'] != $userInfo['isBankrupt']) continue;
            // 是否付费
            if ($bankruptCfg['isRecharge'] != $userInfo['isRecharge']) continue;
            // 注册天数
            if (!Utils::isValueMatched($registerDays, $bankruptCfg['registerDate'])) continue;

            $intervalDays = $userInfo['loginDays'] - ($bankruptcyInfo['iHitBankruptcyDays'] ?? 0);
            if ($intervalDays < $bankruptCfg['loginDays']) {
                continue;
            }

            $userCfg = $bankruptCfg;
            break;
        }

        return $userCfg;
    }

}