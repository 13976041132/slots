<?php

namespace FF\Machines\Features;

use FF\Library\Utils\Utils;

class JackpotPickGame extends BaseFeature
{
    public function onPick($args = [])
    {
        $pickResult = $this->getPickResult();
        $jackpotPrizes = $this->getJackpotPrizes($pickResult['hitJackpots']);
        $featureWin = array_sum(array_column($jackpotPrizes, 'coins'));

        $winInfo = $this->onEnd($featureWin);

        $featureSteps[] = $this->machineObj->addFeatureStep(
            1,
            [ITEM_COINS => $featureWin],
            ['hitJackpot' => implode(',', $pickResult['hitJackpots'])],
            ['prizes' => [ITEM_COINS => $featureWin]]
        );
        $this->machineObj->onFeatureStepsCompleted($this->featureId, array(
            $this->featureId => array('steps' => $featureSteps, 'featureWin' => $featureWin)),
            array('coins' => $featureWin)
        );

        return array(
            'winInfo' => $winInfo,
            'pickResult' => $pickResult,
            'jackpotPrizes' => $jackpotPrizes
        );
    }

    public function autoPick($args = array())
    {
        $pickResult = $this->getPickResult();

        $jackpotPrizes = $this->getJackpotPrizes($pickResult['hitJackpots'], $pickResult['hasWildBoost'] ? 1 : 2);
        $totalWin = array_sum(array_column($jackpotPrizes, 'coins'));

        $featureSteps[] = $this->machineObj->addFeatureStep(
            1,
            [ITEM_COINS => $totalWin],
            ['hitJackpot' => implode(',', $pickResult['hitJackpots'])],
            ['prizes' => [ITEM_COINS => $totalWin]]
        );
        $this->machineObj->onFeatureStepsCompleted($this->featureId, array(
            $this->featureId => array('steps' => $featureSteps, 'featureWin' => $totalWin)),
            array('coins' => $totalWin)
        );

        return array('prizes' => ['coins' => $totalWin], 'pickResult' => $pickResult);
    }

    protected function getPickResult()
    {
        $featureCfg = $this->machineObj->getFeatureConfig($this->featureId);
        $jackpots = array_keys($featureCfg['itemAwardLimit']['jackpotType']);
        $hitJackpots = $this->hitJackpotResult(1, $featureCfg['itemAwardLimit']['jackpotType']);
        $pickTimes = Utils::randByRates($featureCfg['itemAwardLimit']['pickTimes']);

        $pickCards = array();
        foreach ($hitJackpots as $hitJackpot) {
            array_push($pickCards, $hitJackpot, $hitJackpot);
        }

        $remainTimes = $pickTimes - count($pickCards) - 1;
        $noHitJackpots = array_diff($jackpots, $hitJackpots);
        $jackpotValueCnt = [];

        for ($i = 1; $i <= $remainTimes; $i++) {
            shuffle($noHitJackpots);
            $jackpotName = reset($noHitJackpots);
            $pickCards[] = $jackpotName;
            $jackpotValueCnt[$jackpotName] += 1;

            if ($jackpotValueCnt[$jackpotName] == 2) {
                $index = array_search($jackpotName, $noHitJackpots);
                unset($noHitJackpots[$index]);
            }

            if (!$noHitJackpots) break;
        }

        shuffle($pickCards);
        $pickCards = array_merge($pickCards,  $hitJackpots);

        return array(
            'hitJackpots' => $hitJackpots,
            'pickTimes' => count($pickCards),
            'pickCards' => $pickCards,
        );
    }

    protected function hitJackpotResult($hitJackpotNum, $hitJackpotWeights)
    {
        $jackpots = [];
        for ($i = 1; $i <= $hitJackpotNum; $i++) {
            $jackpotName = Utils::randByRates($hitJackpotWeights);
            array_push($jackpots, $jackpotName);
            unset($hitJackpotWeights[$jackpotName]);

            if (!$hitJackpotWeights) {
                break;
            }
        }

        return $jackpots;
    }

    /**
     * 领取jackpot奖励
     */
    protected function getJackpotPrizes($hitJackpots, $multiple = 1)
    {
        $jackpotPrizes = array();
        $jackpots = $this->machineObj->getActiveJackpots();

        foreach ($hitJackpots as $jackpotName) {
            $jackpotId = $jackpots[$jackpotName];
            $pot = $this->machineObj->getJackpotAward($jackpotName);
            $jackpotPrizes[] = array(
                'jackpotId' => $jackpotId, 'jackpotName' => $jackpotName, 'coins' => $pot * $multiple
            );
        }

        return $jackpotPrizes;
    }

}