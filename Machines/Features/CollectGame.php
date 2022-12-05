<?php
/**
 * CollectGame
 */

namespace FF\Machines\Features;

use FF\Factory\Bll;
use FF\Machines\SlotsModel\MachineCollectionTrait;
use FF\Machines\SlotsModel\SlotsMachine;

class CollectGame extends BaseFeature
{
    /**
     * @var SlotsMachine|MachineCollectionTrait $machineObj
     */
    protected $machineObj;

    public function onPlay($args = array())
    {
        $machineObj = $this->machineObj;

        $awardCfg = [];
        if (!empty($args['rewardItem']) && $args['rewardItem'] == 'coins') {
            $awardCfg = ['value' => 1, 'type' => 'coins'];
        }

        $this->checkAwardConfig($awardCfg);
        // 奖励与下一节点信息
        $hitResult = $machineObj->collectGameAward($awardCfg);

        $prizes = $hitResult['prizes'];
        $coinsWin = $prizes[ITEM_COINS] ?: 0;
        $wheelId = $prizes[ITEM_WHEEL]['wheelId'] ?? '';

        if (isset($prizes[ITEM_WHEEL])) {
            $wheelHitResult = $hitResult['prizes'][ITEM_WHEEL]['hitResult'];
            $hitResult['prizes'][ITEM_WHEEL] = $wheelId;
            $prizes = $hitResult['prizes'];
            $coinsWin += $wheelHitResult['prizes'][ITEM_COINS] ?? 0;
            $wheelHitResult['prizes'] = Bll::item()->toList($wheelHitResult['prizes']);
            $hitResult['wheelSpinResult'] = array_merge(['wheelId' => $wheelId], $wheelHitResult);
        }

        if (!empty($prizes[ITEM_FREE_SPIN])) { //freespin次数奖励
            $nextFeatureId = $prizes[ITEM_FREE_SPIN]['featureId'] ?: $machineObj->getFeatureByName(FEATURE_FREE_SPIN);
            $hitResult['winInfo'] = $this->onEnd($coinsWin, $nextFeatureId, array(
                'times' => $prizes[ITEM_FREE_SPIN]['times']
            ));
        } else {
            $winInfo = $this->onEnd($coinsWin);
            $winInfo['winType'] = 0;
            $hitResult['winInfo'] = $winInfo;
        }

        $this->featureStep++;
        $featureSteps[] = $this->machineObj->addFeatureStep($this->featureStep, $prizes, [], $hitResult, $wheelId);
        $this->machineObj->onFeatureStepsCompleted($this->featureId, array(
            $this->featureId => array('steps' => $featureSteps, 'featureWin' => $coinsWin)),
            array('coins' => $coinsWin)
        );

        $hitResult['prizes'] = Bll::item()->toList($hitResult['prizes']);

        if (!isset($hitResult['wheelSpinResult'])) {
            $hitResult['wheelSpinResult'] = [];
        }

        return $hitResult;
    }

    public function autoPlay($args = array())
    {
        $result = array();
        $machineObj = $this->machineObj;

        // 奖励与下一节点信息
        $hitResult = $machineObj->collectGameAward();

        $prizes = $hitResult['prizes'];
        $coinsWin = $prizes[ITEM_COINS] ?: 0;
        $wheelId = $prizes[ITEM_WHEEL]['wheelId'] ?? '';

        if (isset($prizes[ITEM_WHEEL])) {
            $coinsWin += $prizes[ITEM_WHEEL]['hitResult']['prizes'][ITEM_COINS] ?? 0;
            $wheelHitResult = $hitResult['prizes'][ITEM_WHEEL]['hitResult'];
            $hitResult['wheelSpinResult'] = $wheelHitResult;
            $hitResult['prizes'][ITEM_WHEEL] = $wheelId;
        }

        $result['coins'] = $coinsWin;

        if (!empty($prizes[ITEM_FREE_SPIN])) {
            $nextFeatureId = $prizes[ITEM_FREE_SPIN]['featureId'] ?: $machineObj->getFeatureByName(FEATURE_FREE_SPIN);
            $this->onNextFeatureTrigger($nextFeatureId, array(
                'times' => $prizes[ITEM_FREE_SPIN]['times']
            ));
            $result['feature'] = $nextFeatureId;
        }

        $featureSteps[] = $this->machineObj->addFeatureStep($this->featureStep, $prizes, [], $hitResult, $wheelId);
        $this->machineObj->onFeatureStepsCompleted($this->featureId, array(
            $this->featureId => array('steps' => $featureSteps, 'featureWin' => $coinsWin)),
            array('coins' => $coinsWin)
        );

        return $result;
    }

    protected function checkAwardConfig(&$awardCfg)
    {
        //overwrite
    }

}