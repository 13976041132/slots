<?php
/**
 * UltraBet业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

class UltraBetBll
{
    /**
     * 获取 UltraBet 数据
     */
    public function getUltraBetList($uid)
    {
        // 获取配置
        $ultraBetCfg = $this->getUltraBetConfig($uid);

        // 检查用户等级
        $level = Bll::userAdapter()->getUserInfo($uid, 'level');
        if ($level < $ultraBetCfg['enableUltraBetLevel']) {
            return [];
        }

        return $this->initUltraBet($uid, $level);
    }

    /**
     * 初始用户 UltraBet
     */
    public function initUltraBet($uid, $level)
    {
        // 获取配置
        $ultraBetCfg = $this->getUltraBetConfig($uid);

        if (!$ultraBetCfg || $level < $ultraBetCfg['enableUltraBetLevel']) {
            return [];
        }

        $balance = Bll::userAdapter()->getUserInfo($uid, 'coinBalance');
        $maxBet = Bll::machineBet()->getMaxBet($uid, 0, '', $level);

        //余额 <= $maxBet * enableUltraBetMultiple
        if ($balance <= $maxBet * $ultraBetCfg['enableUltraBetMultiple']) {
            return [];
        }

        $ultraBetMaxBet = $balance / $ultraBetCfg['ultraBetRatio'];

        if ($ultraBetMaxBet <= $maxBet) {
            return [];
        }

        $ultraBets = $this->calUltraBets($uid, $maxBet, $ultraBetMaxBet);

        Log::info(['initUltraBet', $uid, $maxBet, $balance, $ultraBets, $ultraBetMaxBet, $ultraBetCfg], 'ultraBet.log');

        return $ultraBets;
    }

    /**
     * 计算 UltraBet
     */
    public function calUltraBets($uid, $maxBet, $ultraBetMaxBet)
    {
        $ultraBets = [];
        $bets = Config::get('machine/common-bet', 'betUnlock/200');

        $betOptions = Bll::machineBet()->getBetOptionList($uid, 0, 'general');
        $betOptions = array_column($betOptions, 'betMultiple', 'totalBet');

        foreach ($bets as $bet) {
            //200级档位的bet
            if ($bet <= $maxBet) continue;
            if ($bet > $ultraBetMaxBet) continue;

            $ultraBets[$bet] = array(
                'totalBet' => $bet,
                'betMultiple' => $betOptions[$bet],
            );
        }

        Log::info(['calUltraBets', $uid, $maxBet, $ultraBetMaxBet, $ultraBets], 'ultraBet.log');

        if (!$ultraBets) {
            return [];
        }

        return $ultraBets;
    }

    /**
     * 获取配置
     */
    public function getUltraBetConfig($uid)
    {
        $ultraBetCfg = config::get('machine/ultra-bet', false);

        $config = array(
            'enableUltraBetLevel' => $ultraBetCfg['enableUltraBetLevel'] ?? 20,
            'enableUltraBetMultiple' => $ultraBetCfg['enableUltraBetMultiple'] ?? 200,
            'ultraBetRatio' => $ultraBetCfg['ultraBetFreeMaxRatio'] ?? 50,
        );

        // 是否付费用户
        $recharge = Bll::userAdapter()->getRechargeInfo($uid, 'totalRecharge');
        if ($recharge > 0) {
            $config['ultraBetRatio'] = $ultraBetCfg['ultraBetPayMaxRatio'] ?? 20;
        }

        return $config;
    }
}