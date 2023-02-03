<?php
/**
 * Feature业务控制器
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Machines\Features\BaseFeature;
use FF\Machines\SlotsModel\LightningMachine;
use GPBClass\Enum\RET;

class FeatureController extends BaseController
{
    /**
     * 恢复FreeGame
     */
    public function resumeFreeGame()
    {
        $machineObj = $this->getMachineObj();
        if (!$machineObj->isInFreeGame()) {
            FF::throwException(RET::RET_FAILED);
        }

        $freespinInfo = $machineObj->getFreespinInfo();
        $currFeature = $machineObj->getCurrFeature();
        $totalWin = $machineObj->getTotalWin();
        $settled = false;

        //当剩余freespin次数为0时，进行结算
        if ($machineObj->isFreeGame($currFeature)) {
            if ($machineObj->isLastFreeSpin() && $machineObj->isSpinAble()) {
                $settled = true;
            }
        }

        if ($settled) {
            $machineObj->onFreespinOver();
            $winType = $machineObj->coinsSettlement($totalWin);
            $detail = array();
        } else {
            $detail = $machineObj->getFreeGameDetailOnResume();
            $winType = 0;
        }

        $totalBet = $machineObj->getTotalBet();
        $resumeBet = $machineObj->getGameInfo('resumeBet');
        $totalBet = $resumeBet ?: $totalBet;

        return array(
            'spinTimes' => $freespinInfo['spinTimes'],
            'totalTimes' => $freespinInfo['totalTimes'],
            'detail' => $detail ? json_encode($detail) : '{}',
            'totalBet' => $totalBet,
            'totalWin' => $totalWin,
            'winType' => $winType,
            'settled' => $settled,
        );
    }

    /**
     * 结束features
     */
    public function featuresOver()
    {
        $machineObj = $this->getMachineObj();
        if (!$machineObj->getCurrFeature()) {
            FF::throwException(RET::RET_FAILED);
        }

        if (!$totalBet = $machineObj->getGameInfo('resumeBet')) {
            $totalBet = $machineObj->getTotalBet();
        }

        $bakFeatures = $machineObj->getGameInfo('bakFeatures');
        $currFeature = $machineObj->getCurrFeature();
        $featureIds = array_merge(array_column($bakFeatures, 'featureId'), [$currFeature]);
        $prizes = [];
        $featureWin = 0;

        foreach ($featureIds as $featureId) {
            if ($machineObj->isFreeGame($featureId)){
                $machineObj->clearFreespin();
            }
            $featureCfg = $machineObj->getFeatureConfig($featureId);
            $winCoins = round($featureCfg['multiple'] * $totalBet / 100) * 100;
            $featureWin += $winCoins;
            $prizes[] = array(
                'featureId' => $featureId,
                'coinsWin' => $winCoins
            );
        }

        $machineObj->clearBakFeatures();
        $result = (new BaseFeature($machineObj, $currFeature))->onEnd($featureWin);
        $machineObj->saveGameInfo();

        return array(
            'totalWin' => $result['totalWin'],
            'featureWin' => $featureWin,
            'featuresPrize' => $prizes,
            'winType' => $result['winType'],
            'settled' => $result['settled']
        );
    }

    /**
     * 恢复Lightning
     */
    public function recoverLightning()
    {
        /**
         * @var $machineObj LightningMachine
         */
        $machineObj = $this->getMachineObj();

        $featureId = $machineObj->getCurrFeature();

        if (!$featureId) FF::throwException(RET::FAILED);
        if (!method_exists($machineObj, 'isLightning')) FF::throwException(RET::FAILED);
        if (!$machineObj->isLightning($featureId)) {
            FF::throwException(RET::FAILED, 'featureId = ' . $featureId);
        }

        $data = $machineObj->getFeaturePlugin($featureId)->onResume();

        return $data;
    }

    /**
     * 进行一次Hold&Spin
     */
    public function holdAndSpin()
    {
        /**
         * @var $machineObj LightningMachine
         */
        $machineObj = $this->getMachineObj();

        $featureId = $machineObj->getCurrFeature();

        if (!$featureId) FF::throwException(RET::FAILED);
        if (!method_exists($machineObj, 'isLightning')) FF::throwException(RET::FAILED);
        if (!$machineObj->isLightning($featureId)) FF::throwException(RET::FAILED);

        $data = $machineObj->getFeaturePlugin($featureId)->onSpin();

        return $data;
    }

    public function playPickGame()
    {
        $machineObj = $this->getMachineObj();
        $featureId = $machineObj->getCurrFeature();

        if (!$featureId || $this->getMachineObj()->getFeatureName($featureId) != FEATURE_PICK_GAME) {
            FF::throwException(RET::FAILED);
        }

        return $machineObj->getFeaturePlugin($featureId)->onPick();
    }

    /**
     * 转盘spin
     */
    public function wheelSpin()
    {
        $uid = $this->getUid();
        $wheelId = $this->getParam('wheelId');
        $pos = $this->getParam('pos', false);

        $wheelInfo = Bll::wheel()->getWheelInfo($wheelId);
        if (!$wheelInfo) {
            FF::throwException(RET::PARAMS_INVALID);
        }

        //该转盘不允许直接调用wheelSpin接口
        if ($wheelInfo['wheelSpinEnable'] != 'Y') {
            FF::throwException(RET::RET_FAILED);
        }

        if (FF::isProduct()) $pos = null;
        $result = Bll::wheel()->onSpin($uid, $wheelId, 0, $pos);

        return $result;
    }

}