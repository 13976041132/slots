<?php
/**
 * Feature模型
 */

namespace FF\Machines\Features;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Feature;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use FF\Machines\SlotsModel\SlotsMachine;
use FF\Service\Lib\Service;

class BaseFeature
{
    /**
     * @var SlotsMachine
     */
    protected $machineObj;

    protected $featureId;

    protected $featureNo;

    protected $machineId;

    protected $uid;

    protected $featureStep;

    public function __construct($machineObj, $featureId, $data = array())
    {
        $this->machineObj = $machineObj;
        $this->featureId = $featureId;

        if ($machineObj) {
            $this->machineObj->initFeatureOptions();
            if ($this->machineObj->getGameInfo('featureId') == $featureId) {
                $this->featureNo = $this->machineObj->getGameInfo('featureNo');
            } else {
                $this->featureNo = '';
            }
            $this->machineId = $this->machineObj->getMachineId();
            $this->uid = $this->get('uid');
        } else {
            $this->featureNo = $data['featureNo'] ?? '';
            $this->machineId = $data['machineId'];
            $this->uid = 0;
        }

        $this->featureStep = 0;
    }

    /**
     * 获取绑定的机台实例ID
     */
    public function getMachineInstanceId()
    {
        return $this->machineObj->getInstanceId();
    }

    /**
     * 获取机台属性
     */
    public function get($field)
    {
        return $this->machineObj->getMember($field);
    }

    /**
     * 获取redis实例
     */
    public function redis()
    {
        return Dao::redis('game');
    }

    /**
     * feature数据初始化
     * to override
     */
    protected function init($args = array())
    {
        return $args;
    }

    /**
     * feature触发时的处理
     */
    public function onTrigger($args = array())
    {
        if ($this->machineObj->isVirtualMode) {
            return;
        }

        $featureDetail = $this->init($args);

        //若此feature同时也是FreeGame，在onFreeGameTriggered中已处理过了，此处不再处理
        if (!$this->machineObj->isFreeGame($this->featureId)) {
            $featureNo = $this->machineObj->genFeatureNo();
            $this->machineObj->setFeature($this->featureId, $featureDetail, null, $featureNo);
            $this->featureNo = $featureNo;
        }
    }

    /**
     * feature结束时的处理
     */
    public function onEnd($featureWin, $nextFeatureId = '', $nextFeatureDetail = array())
    {
        $_this = $this->machineObj;

        $totalWin = $_this->getTotalWin();
        $coinsWin = $_this->getGameInfo('coinsWin');

        //FreeGame过程中已经累积了totalWin，不能重复加
        if (!$_this->isFreeGame($this->featureId)) {
            $totalWin += $featureWin;
            $coinsWin += $featureWin;
        }

        $settled = !$nextFeatureId;

        $_this->onFeatureEnd($this->featureId, $settled);

        $this->checkSettlement($settled);

        if ($settled) {
            $_this->coinsSettlement($totalWin);
            if ($resumeBet = $_this->getGameInfo('resumeBet')) {
                $_this->setTotalBet($resumeBet);
            }
            //更新触发该feature当次的spin记录的totalWin与balance
            if ($betId = $_this->getGameInfo('betId')) {
                Bll::betLog()->onFeatureEnd($this->uid, $betId, $coinsWin, $totalWin, $settled, $_this->getBalance());
            }
        } else {
            $_this->setTotalWin($totalWin);
        }

        if ($nextFeatureId) {
            $this->onNextFeatureTrigger($nextFeatureId, $nextFeatureDetail);
        }

        //当玩家可以进行下一次spin时，认为本次spin已经结束
        if ($this->machineObj->isSpinAble()) {
            $_this->updateGameInfo(array('coinsWin' => 0));
            $spinEnd = true;
        } else {
            $_this->updateGameInfo(array('coinsWin' => $coinsWin));
            $spinEnd = false;
        }

        //大奖类型
        $winType = 0;
        if ($spinEnd) {
            if ($_this->isInFreeGame()) {
                $winType = $_this->getWinType($coinsWin);
            } else {
                $winType = $_this->getWinType($totalWin);
            }
        }

        //广告加成倍数
        $adMultiple = $this->machineObj->getBetContext('adMultiple');

        $this->destroy();

        $result = array(
            'featureWin' => $featureWin,
            'coinsWin' => $coinsWin,
            'totalWin' => $totalWin,
            'winType' => $winType,
            'adMultiple' => $adMultiple ?: 0,
            'spinEnd' => $spinEnd,
            'settled' => $settled,
        );

        return $result;
    }

    /**
     * 在本feature中触发了其他feature
     */
    public function onNextFeatureTrigger($featureId, $featureDetail = array())
    {
        $_this = $this->machineObj;

        if ($_this->isFreeGame($featureId)) {
            $times = $featureDetail['times'] ?? $_this->getFreespinInitTimes($featureId);
            $_this->onFreeGameTriggered($featureId, $times, $featureDetail);
        } else {
            Feature::clearInstanceByFeature($_this->getInstanceId(), $featureId);
            $_this->onFeatureTriggered($featureId);
        }
    }

    /**
     * 恢复feature时获取数据
     * to override
     */
    public function onResume()
    {
        return array();
    }

    /**
     * feature中进行选择操作时的处理
     */
    public function onChoose($choosed)
    {
        return array();
    }

    /**
     * feature中spin时的处理
     * to override
     */
    public function onSpin($args = array())
    {
        return array();
    }

    /**
     * feature中pick时的处理
     * to override
     */
    public function onPick($args = array())
    {
        return array();
    }

    /**
     * feature中play时的处理
     * to override
     */
    public function onPlay($args = array())
    {
        return array();
    }

    public function autoChoose(&$featureDetail = null)
    {
        //to override
    }

    /**
     * feature自动完成
     */
    public function autoPlay($args = array())
    {
        return array();
    }

    /**
     * 自动选择
     * @param array $args
     * @return array
     */
    public function autoPick($args = array())
    {
        return array();
    }

    /**
     * feature自动结束
     */
    public function autoEnd()
    {
        //to override
    }

    /**
     * 检查featureNo
     */
    public function checkFeatureNo()
    {
        if (!$this->featureNo) {
            FF::throwException(Code::SYSTEM_ERROR);
        }
    }

    protected function checkSettlement(&$settled)
    {
        //overwrite
    }

    /**
     * 销毁本实例
     */
    public function destroy()
    {
        $machineInstanceId = $this->machineObj->getInstanceId();

        Feature::clearInstanceByFeature($machineInstanceId, $this->featureId);
        Log::info("destroy feature instance, machineInstanceId = {$machineInstanceId}, featureId = {$this->featureId}",'slotsGame.log');
    }
}