<?php
/**
 * TooRich 轴干预
 */

namespace FF\Bll\Intervene;

use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;

class TooRichBll extends InterveneBll
{
    protected $type = 'TooRich';

    const TRIGGER_REASON_RELATIVE = 'Relative';
    const TRIGGER_REASON_ABSOLUTE = 'Absolute';
    const TRIGGER_REASON_WIN = 'Win';

    /**
     * 检测玩家是否触发了资产过多
     * 并识别触发原因[相对资产过多|绝对资产过多|赢取资产过多]
     */
    public function checkInterveneTrigger($uid, $userInfo)
    {
        // 初始返回格式
        $result = parent::checkInterveneTrigger($uid, $userInfo);

        $reasons = $this->getTooRichReasons($userInfo, $warningValue);
        if (!$reasons) return $result;

        $interveneCfg = $this->getBaseInterveneCfg();
        foreach ($reasons as $reason) {
            $times = $this->addDailyInterveneTimes($uid, $reason);
            if ($times == 1) {
                $flag = Utils::isHitByRate($interveneCfg['trigProbability']) ? 'Y' : 'N';
                $interveneInfo = array(
                    'reason' => $reason,
                    'warningValue' => $warningValue,
                    'initBalance' => $userInfo['initBalanceToday'],
                    'profits' => 0,
                    'flag' => $flag
                );
                if ($flag == 'N') {
                    $interveneInfo['expireTime'] = time() + 86400;
                }
                Log::info([$this->type, 'Trigger', $uid, $userInfo, $interveneInfo], 'intervene.log');
                $this->initInterveneInfo($uid, $interveneInfo);
                $this->onInterveneTrigger($uid, $reason);

                $result['isIntervene'] = true;
                $result['interveneFlag'] = $flag;
                $result['interveneType'] = $interveneCfg['trigOptions']['trigType'];
                $result['interveneValue'] = $flag == 'Y' ? $interveneCfg['trigOptions']['Sample_Group'] : 'Normal';
                return $result;
            }
        }

        return $result;
    }

    /**
     * 计算资产过多警戒值
     * 玩家VIP级别越高，警戒值越高
     * 玩家等级会给警戒值带来加成，等级越高加成越高
     */
    public function calWarningValue($userInfo)
    {
        $trigItemNumCfg = $this->getBaseInterveneCfg('trigItemNum');
        if (!$trigItemNumCfg) return 0;

        $coins = Utils::matchValueByRect($userInfo['vip'], $trigItemNumCfg['VIP']);
        $addition = Utils::matchValueByRect($userInfo['level'], $trigItemNumCfg['level']);

        return $coins * (1 + $addition);
    }

    public function getTooRichReasons($userInfo, &$warningValue = null)
    {
        $warningValue = $this->calWarningValue($userInfo);

        //用户资产必须>=警戒值，才能触发资产过多
        if (!$warningValue || $userInfo['balance'] < $warningValue) {
            return [];
        }

        $reasons = [];
        $warnRatio = $userInfo['balance'] / $warningValue;
        $betRatio = $userInfo['balance'] / ($userInfo['suggestBet'] ?: 10000);
        $winRatio = $userInfo['profitToday'] / ($userInfo['initBalanceToday'] ?: 10000);
        $trigCondition = $this->getBaseInterveneCfg('trigCondition');

        if (Utils::isValueMatched($betRatio, $trigCondition['suggestBetRatio'])) {
            $reasons[] = self::TRIGGER_REASON_RELATIVE;
        }
        if (Utils::isValueMatched($warnRatio, $trigCondition['currentCoinRatio'])) {
            $reasons[] = self::TRIGGER_REASON_ABSOLUTE;
        }
        if (Utils::isValueMatched($winRatio, $trigCondition['winCoinRatio'])) {
            $reasons[] = self::TRIGGER_REASON_WIN;
        }

        return $reasons;
    }

    /**
     * 检测玩家是否需要退出资产过多干预
     */
    public function checkInterveneExit($uid, $interveneInfo, $userInfo)
    {
        if (!$interveneInfo) return true;

        $exit = false;
        $reason = null;
        $triggerReason = $interveneInfo['reason'] ?? '';
        $warnRatio = $userInfo['balance'] / ($interveneInfo['warningValue'] ?: 100000000);
        $betRatio = $userInfo['balance'] / ($userInfo['suggestBet'] ?: 10000);
        $winRatio = $interveneInfo['profits'] / ($interveneInfo['initBalance'] ?: 10000);
        $endCondition = $this->getBaseInterveneCfg('endCondition');

        if (empty($interveneInfo['flag']) || !$endCondition) {
            $reason = 'Error';
            $exit = true;
        } elseif ($interveneInfo['flag'] == 'N' && $interveneInfo['expireTime'] <= time()) {
            $reason = 'Expired';
            $exit = true;
        } elseif (Utils::isValueMatched($warnRatio, $endCondition['warnCoinRatio'])) {
            $reason = 'WarningBreak';
            $exit = true;
        } elseif ($triggerReason == self::TRIGGER_REASON_RELATIVE) {
            if (Utils::isValueMatched($betRatio, $endCondition['suggestBetRatio'])) {
                $reason = 'BetRatioBreak';
                $exit = true;
            }
        } elseif ($triggerReason == self::TRIGGER_REASON_WIN) {
            if (Utils::isValueMatched($winRatio, $endCondition['winCoinRatio'])) {
                $reason = 'WinCoinsBreak';
                $exit = true;
            }
        }

        if ($exit) {
            Log::info([$this->type, 'Exit', $uid, $userInfo, $interveneInfo, $reason], 'intervene.log');
            $this->clearInterveneInfo($uid);
            $this->onInterveneExit($uid, $reason);
        }

        return $exit;
    }
}