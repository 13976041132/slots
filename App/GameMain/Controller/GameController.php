<?php
/**
 * 游戏控制器
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Machines\SlotsModel\MachineCollectionTrait;
use FF\Machines\SlotsModel\SlotsMachine;
use GPBClass\Enum\MSG_ID;
use GPBClass\Enum\RET;

class GameController extends BaseController
{
    /**
     * 进入机台
     */
    public function enterMachine()
    {
        $uid = $this->getUid();
        $machineId = $this->getParam('machineId');
        $machineObj = Bll::machine()->getMachineInstance($uid, $machineId);
        $machineObj->checkFeatureError();
        list($betMultiple, $totalBet) = $machineObj->getSuggestBetOnEnter();

        if (!$totalBet || !$betMultiple) {
            FF::throwException(RET::RET_SYSTEM_ERROR);
        }

        $machineObj->onEnter();

        //当前feature信息
        $bakFeatures = $machineObj->getBakFeatures();
        $currFeature = $machineObj->getCurrFeature();
        $featureInfo = array(
            'featureId' => $currFeature,
            'bakFeatures' => $bakFeatures
        );

        //机台元素初始化，支持从feature中恢复上次spin结果
        if (!($elements = $machineObj->getGameInfo('lastSpinElements'))) {
            $elements = $machineObj->getInitElements();
        }

        //feature中悬浮在轴上的元素
        if ($stickyElements = $machineObj->getFeatureDetail('stickyElements')) {
            $stickyElements = $machineObj->elementsToList($stickyElements);
        }

        $data = array(
            'machineId' => $machineId,
            'betOptions' => $machineObj->getBetOptionList(),
            'betMultiple' => $betMultiple,
            'totalBet' => $totalBet,
            'resumeBet' => $machineObj->getGameInfo('resumeBet'),
            'totalWin' => $machineObj->getTotalWin(),
            'featureInfo' => $featureInfo,
            'elements' => $elements,
            'stickyElements' => $stickyElements ?: [],
            'ultraBetOptions' => $machineObj->getUltraBetOptionList(),
            'allBetOptions' => $machineObj->getAllBetOptionList(),
            'coins' => $machineObj->getUserInfo('coins'),
            'diamond' => $machineObj->getUserInfo('diamond'),
            'nextMessages' => [MSG_ID::MSG_INIT_JACKPOTS => ['jackpots' => array_values($machineObj->getJackpots())]]
        );

        return $data;
    }

    /**
     * 退出机台
     */
    public function exitMachine()
    {
        $machineObj = $this->getMachineObj();

        $data = $machineObj->onExit();

        Bll::session()->save(array('machineId' => 0, 'isSpinning' => false));

        return $data;
    }

    /**
     * 获得下注选项
     */
    private function getRunOptions()
    {
        $winType = $this->getParam('winType', false, 0);
        $feature = $this->getParam('feature', false);
        $clear = $this->getParam('clear', false);
        $hit = $this->getParam('hit', false);

        if ($winType || $feature || $clear || $hit) {
            $machineObj = $this->getMachineObj();
            if ($clear) {
                $machineObj->clearFeature();
                $machineObj->clearFreespin();
            }
        }

        $options = array();

        if ($winType > 0 && $winType <= 3) {
            $options['winType'] = $winType;
            $hit = true;
        }

        if ($feature) {
            $options['features'] = array_filter(explode(',', $feature));
        }
        if ($hit) {
            $options['hit'] = $hit;
        }

        return $options;
    }

    /**
     * 机台下注
     */
    public function slotsBetting()
    {
        $lastSpinTime = Bll::session()->get('spinTime') ?? 0;
        //防止存在同个账号并发
        if (Bll::session()->get('isSpinning') && $lastSpinTime > time() - 3) {
            FF::throwException(RET::FAILED, 'player spin  disable, Because spin is not over yet');
        }

        if ($this->getMachineObj()->getMember('unlockLevel') > Bll::user()->getLevel($this->getUid())) {
            FF::throwException(RET::FAILED, 'machine is not unlocked');
        }

        Bll::session()->save(['isSpinning' => true, 'spinTime' => time()]);

        $totalBet = $this->getParam('totalBet', false, 0);
        $options = !FF::isProduct() ? $this->getRunOptions() : array();
        $data = $this->getMachineObj()->run($totalBet, $options);

        Bll::session()->save(['isSpinning' => false]);

        return $data;
    }

    public function initJackpots()
    {
        $machineObj = $this->getMachineObj();
        $jackpots = array_values($machineObj->getJackpots());

        return array(
            'jackpots' => $jackpots
        );
    }

    /**
     * 初始机台内收集
     */
    public function initCollectGame()
    {
        /**
         * @var SlotsMachine|MachineCollectionTrait $machineObj
         */
        $machineObj = $this->getMachineObj();
        if (!method_exists($machineObj, 'getCollectGameNodes')
            || !method_exists($machineObj, 'getCollectGameInfo')) {
            FF::throwException(RET::FAILED);
        }

        $collectInfo = $machineObj->getCollectGameInfo();
        $nodesInfo = $machineObj->getCollectGameNodes();

        return array(
            'collectInfo' => $collectInfo,
            'nodesInfo' => $nodesInfo
        );
    }

}