<?php
/**
 * Slots干预层
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Framework\Utils\Log;

abstract class SlotsIntervene extends SlotsDecider
{
    protected $interveneInfo = array();

    public function clearBuffer()
    {
        $this->interveneInfo = [];

        parent::clearBuffer();
    }

    /**
     * spin开始时进行干预检测
     */
    public function checkInterveneOnBegin()
    {
        // 是否启用干预 与 轴样本 判断
        if ((defined('IV_ENABLE') && !IV_ENABLE)) {
            return;
        }

        // 检查基础干预列表
        $this->checkBaseIntervene();

        // 检查事件干预
        $this->checkEventIntervene();

        // 检查 FeatureGme 时的干预
        $this->checkFreeGameIntervene();
    }

    /**
     * spin结束时进行干预检测
     */
    public function checkInterveneOnEnd($totalWin, $winType)
    {
        if ((defined('IV_ENABLE') && !IV_ENABLE)) {
            return;
        }

        if ($this->isFreeSpin() && !$this->isLastFreeSpin()) {
            return;
        }

        $this->updateInterveneInfo($totalWin);

        $this->checkBankruptcyInterveneExit();
        $this->checkRechargeInterveneExit();
    }

    public function checkRechargeInterveneExit()
    {
        if (empty($this->betContext['interveneType']) || $this->betContext['interveneType'] !== 'Recharge') return;

    }

    public function checkBankruptcyInterveneExit()
    {

    }

    /**
     * 检查当前 Feature 干预
     */
    public function checkFreeGameIntervene()
    {
        if (!$this->isInFreeGame()) return;

        // 检查 FreeGame 不中奖时，进行干预
        if ($this->isLastFreeSpin() && $this->getTotalWin() <= 0) {
            $freeSpinInfo = $this->getFreespinInfo();
            if ($freeSpinInfo['initTimes'] != 1) {
                $this->runOptions['hit'] = true;
            }
        }
    }

    /**
     * 检查基础干预列表
     */
    public function checkBaseIntervene()
    {
        // 跳过 FreeGame
        if ($this->isFreeSpin()) return;

        // 新手轴干预
        if ($this->checkNoviceIntervene()) {
            return;
        }

        // 过渡轴干预
        if ($this->checkTransitionIntervene()) {
            return;
        }

        // TooRich 干预
        if ($this->checkTooRichIntervene()) {
            return;
        }
    }

    /**
     * 检查事件干预
     */
    public function checkEventIntervene()
    {
        // 跳过 FreeGame
        if ($this->isFreeSpin()) return;

        //新手事件干预
        if ($this->checkNoviceEventIntervene()) {
            return;
        }

        //付费干预检测
        //触发付费干预时，不再进行其它任何干预
        if ($this->checkRechargeEventIntervene()) {
            return;
        }

        //新机台体验干预检测
        //触发新机台体验干预时，不再进行其它任何干预
        if ($this->checkExperienceEventIntervene()) {
            return;
        }

        //破产干预检测
        if ($this->checkBankruptEventIntervene()) {
            return;
        }

        //极端体验干预检测
        $this->checkExtremeEventIntervene();
    }

    /**
     * 指定进行普通中奖干预
     */
    public function setHitIntervene($isHit = true)
    {
        $this->runOptions['hit'] = $isHit;
    }

    /**
     * 指定进行feature干预
     */
    public function setFeatureIntervene($featureName)
    {
        $featureId = $this->getFeatureByName($featureName);
        if ($featureId) {
            $this->runOptions['features'] = [$featureId];
        }
    }

    /**
     * 指定进行 WinMulti 干预
     */
    public function setWinMultiIntervene($winMulti)
    {
        $this->runOptions['hit'] = true;
        $this->runOptions['winMulti'] = $winMulti;
    }

    /**
     * 指定进行 WinType 干预
     */
    public function setWinTypeIntervene($winType)
    {
        $this->runOptions['hit'] = true;
        $this->runOptions['winType'] = $winType;
    }

    /**
     * 指定进行 SampleGroup 干预
     */
    public function setSampleIntervene($sampleGroup)
    {
        $this->runOptions['sampleGroup'] = $sampleGroup;
    }

    /**
     * @uses setWinTypeIntervene,setSampleIntervene,setWinMultiIntervene,setFeatureIntervene,setHitIntervene
     * 处理 干预 数据
     */
    public function dealEventIntervene($interveneType, $interveneResult)
    {
        // 检查干预
        if (!$interveneResult['isIntervene']) {
            return false;
        }

        $this->interveneInfo[$interveneResult['interveneType']] = $interveneResult;

        $method = 'set' . ucfirst($interveneResult['interveneType']) . 'Intervene';
        if (method_exists($this, $method)) {
            $this->$method($interveneResult['interveneValue']);
        }
        $this->betContext['isIntervene'] = true;
        $this->betContext['interveneType'] = $interveneType;

        // 初始干预
        Log::info([$interveneType, $this->uid, $this->balance, $this->machineId, $interveneResult], 'intervene.log');
        return true;
    }

    /**
     * @uses setWinTypeIntervene,setSampleIntervene,setWinMultiIntervene,setFeatureIntervene,setHitIntervene
     * 处理 基础干预
     */
    public function dealBaseIntervene($interveneResult)
    {
        // 检查干预
        if (!$interveneResult['isIntervene']) {
            return false;
        }

        $this->interveneInfo[$interveneResult['interveneType']] = $interveneResult;

        $method = 'set' . ucfirst($interveneResult['interveneType']) . 'Intervene';

        if (method_exists($this, $method)) {
            $this->$method($interveneResult['interveneValue']);
        }

        return true;
    }

    /**
     * 检查是否需要进行新手干预
     */
    public function checkNoviceIntervene()
    {
        // 已结束干预,testMode 判断
        if (!empty($this->analysisInfo['noviceEnded'])) return false;

        $result = Bll::ivNovice()->checkInterveneTrigger($this->uid, array(
            'machineId' => $this->machineId,
            'spinTimes' => $this->analysisInfo['spinTimes'] + 1,
        ));

        // 执行干预内容
        return $this->dealBaseIntervene($result);
    }

    /**
     * 检查是否需要进行过渡轴干预
     */
    public function checkTransitionIntervene()
    {
        //todo
        return false;
    }

    /**
     * 检查是否需要进行 TooRich 干预
     */
    public function checkTooRichIntervene()
    {
        //todo
        return false;
    }

    /**
     * 检查是否需要进行付费干预
     */
    public function checkRechargeEventIntervene()
    {
        if (!empty($this->runOptions['ivTypes']) && !in_array('Recharge', $this->runOptions['ivTypes'])) {
            return false;
        }

        $result = Bll::ivRechargeEvent()->checkInterveneTrigger($this->uid, array(
            'machineId' => $this->machineId,
            'rechargePlayNum' => Bll::userAdapter()->getRechargeInfo($this->uid, 'rechargePlayNum') ?: 0,
        ));

        $isIntervene = $this->dealEventIntervene('Recharge', $result);

        return $isIntervene;
    }

    /**
     * 检查是否需要进行新机台体验干预
     */
    public function checkExperienceEventIntervene()
    {
        if (!empty($this->runOptions['ivTypes']) && !in_array('Experience', $this->runOptions['ivTypes'])) {
            return false;
        }

        //机台独立干预
        $result = Bll::ivExperienceEvent()->checkInterveneTrigger($this->uid, array(
            'machineId' => $this->machineId,
            'spinTimes' => $this->gameInfo['spinTimes'],
            'featureTimes' => $this->gameInfo['featureTimes'],
        ));

        return $this->dealEventIntervene('Experience', $result);
    }

    /**
     * 检查是否需要进行极端体验干预
     */
    public function checkExtremeEventIntervene()
    {
        if (!empty($this->runOptions['ivTypes']) && !in_array('Extreme', $this->runOptions['ivTypes'])) {
            return false;
        }

        return false;
    }

    /**
     * 检查是否需要进行破产保护干预
     */
    public function checkBankruptEventIntervene()
    {
        // 检查冷却期
        // 检查金币是否不足
        // 通过用户属性，获取干预概率，进行干预判断
        // 进入干预，设置干预初始数据（balance，bet，bigWin目标）
        // 进入干预情况，相对破产发生时，检查是否触发退出（完成对应倍数，余额满足倍数，提升bet值到指定倍数）
        // 触发 feature，修改触发状态，在结束后记录倍数，确定是否满足

        if (!empty($this->runOptions['ivTypes']) && !in_array('Bankrupt', $this->runOptions['ivTypes'])) {
            return false;
        }

        $checkData = array(
            'machineId' => $this->machineId,
            'totalBet' => $this->getTotalBet(),
            'betIndex' => $this->getTotalBetIndex(),
            'betRatio' => $this->betContext['betRatio'],
            'isRecharge' => Bll::userAdapter()->getRechargeInfo($this->uid, 'times') > 0,
            'registerTime' => Bll::userAdapter()->getUserInfo($this->uid, 'createTime') ?: time(),
            'loginDays' => (int)$this->getUserInfo('loginDays'),
            'spinTimes' => $this->analysisInfo['spinTimes'],
            'balance' => $this->balance,
            'winMultiples' => json_decode($this->machine['winMultiples'], true),
        );

        if (isset($checkData['interveneInfo']['iHitBankruptcyBet'])) {
            $checkData['interveneInfo']['betIndex'] = $this->getTotalBetIndex($checkData['interveneInfo']['iHitBankruptcyBet']);
        }

        $result = Bll::ivBankruptEvent()->checkInterveneTrigger($this->uid, $checkData);



        return $this->dealEventIntervene('Bankrupt', $result);
    }

    /**
     * spin结束后更新干预信息
     */
    public function updateInterveneInfo($totalWin)
    {

    }

    /**
     * 检查是否需要进行新手干预
     */
    public function checkNoviceEventIntervene()
    {
        if (!empty($this->runOptions['ivTypes']) && !in_array('Novice', $this->runOptions['ivTypes'])) {
            return false;
        }

        $result = Bll::ivNoviceEvent()->checkInterveneTrigger($this->uid, array(
            'machineId' => $this->machineId,
            'spinTimes' => $this->analysisInfo['spinTimes'] + 1,
            'regCoins' =>$this->analysisInfo['regCoins'],
            'balance' => $this->balance,
        ));

        // 执行干预内容
        return $this->dealEventIntervene('Novice', $result);
    }

    /**
     * 检查 feature 限制
     */
    public function checkFeatureInInWinMultipleIntervene()
    {
        // 去除预触发掉落的 feature
        $features = array();
        foreach ($this->betContext['preFeatures'] as $featureId) {
            $featureCfg = $this->getFeatureConfig($featureId);
            if (!empty($featureCfg['itemAward'])) continue;
            $features[] = $featureId;
        }
        $this->betContext['preFeatures'] = $features;
    }

    /**
     * 检查赢取目标结果
     */
    public function checkWinTargetResult()
    {
        //如果牌面的是同一种元素 不需要校验winMultiple
        $randomElements = $this->interveneInfo['winMulti'] ?? [];
        if (!in_array('*', $randomElements)) {
            return true;
        }

        $coinsWin = $this->betContext['totalWin'] - $this->gameInfo['totalWin'];
        $winMultiple = round($coinsWin / $this->betContext['totalBet'], 2);
        $isValid = (isset($this->runOptions['winType']) && $this->getWinType($coinsWin) == $this->runOptions['winType']) ||
            (isset($this->runOptions['winMulti']) && $winMultiple >= $this->runOptions['winMulti']);

        return $isValid;
    }
}