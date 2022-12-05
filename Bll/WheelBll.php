<?php
/**
 * 转盘业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;
use FF\Machines\SlotsModel\SlotsMachine;
use GPBClass\Enum\RET;

class WheelBll
{
    public function getWheelInfo($wheelId)
    {
        return Config::get('feature/wheels', $wheelId);
    }

    public function getWheelItems($wheelId)
    {
        $items = Config::get('feature/wheel-items', $wheelId);
        if (!$items) return array();

        return $items;
    }

    public function onSpin($uid, $wheelId, $addition = 0, $pos = null)
    {
        $wheelInfo = $this->getWheelInfo($wheelId);

        if (!$wheelInfo) FF::throwException(RET::PARAMS_INVALID);

        $machineId = $wheelInfo['machineId'];

        if (!empty($wheelInfo['feature'])) {
            $hitResult = $this->spinInFeature($uid, $machineId, $wheelId, $pos);
            $hitResult['prizes'] = Bll::item()->toList($hitResult['prizes']);
        } else {
            $hitResult = $this->getHitResult($wheelId, null, $addition, $pos);
            $prizes = $hitResult['prizes'];
            $hitResult['prizes'] = Bll::item()->addItems($uid, $prizes, $wheelInfo['wheelName']);
            $hitResult['winInfo'] = array(
                'settled' => true
            );
        }

        $hitResult['cost'] = 0;

        return $hitResult;
    }

    /**
     * 在feature中进行转盘spin
     */
    public function spinInFeature($uid, $machineId, $wheelId, $pos)
    {
        $machineObj = Bll::machine()->getMachineInstance($uid, $machineId);
        $featureId = $machineObj->getCurrFeature();
        $featureDetail = $machineObj->getFeatureDetail();

        if (!$featureId) FF::throwException(RET::FAILED);
        if (empty($featureDetail['wheelId'])) FF::throwException(RET::FAILED);
        if ($featureDetail['wheelId'] != $wheelId) FF::throwException(RET::FAILED);

        $args = array('wheelId' => $wheelId, 'pos' => $pos);
        $hitResult = $machineObj->getFeaturePlugin($featureId)->onSpin($args);

        return $hitResult;
    }

    /**
     * 获取转盘命中结果
     * @param $machineObj SlotsMachine
     */
    public function getHitResult($wheelId, $machineObj = null, $addition = 0, $pos = null)
    {
        $wheelItems = $this->getWheelItems($wheelId);

        if (!$wheelItems) FF::throwException(RET::PARAMS_INVALID);

        //转盘元素值与权重可能根据下注额分档
        if ($machineObj) {
            $index = $machineObj->getTotalBetIndex();
            $jackpots = $machineObj->getActiveJackpots();
            foreach ($wheelItems as $k => $item) {
                $weights = explode(',', $item['weight']);
                $values = explode(',', $item['itemValue']);
                $wheelItems[$k]['weight'] = isset($weights[$index]) ? $weights[$index] : array_pop($weights);
                $wheelItems[$k]['itemValue'] = isset($values[$index]) ? $values[$index] : array_pop($values);
                // jackpot 奖励需要进行解锁检查
                if ($item['itemType'] == 'Jackpot' && !isset($jackpots[$item['itemValue']])) {
                    $wheelItems[$k]['weight'] = 0;
                }
            }
        }

        $weights = array_column($wheelItems, 'weight', 'pos');

        if (!$pos || !is_numeric($pos) || !isset($weights[$pos])) {
            $pos = (int)Utils::randByRates($weights);
        }

        //中奖元素列表
        //单个转盘格子里可能有多项奖励
        $hitItems = array();
        $wheelInfo = $this->getWheelInfo($wheelId);
        foreach ($wheelItems as $item) {
            if ($item['pos'] == $pos) {
                $hitItems[] = array(
                    'itemType' => $item['itemType'],
                    'itemValue' => $item['itemValue'],
                    'itemId' => $item['itemId'],
                );
            }
        }

        //获得的奖励集合
        $prizes = $this->getPrizes($hitItems, $wheelInfo['machineId'], $machineObj, $addition);

        if (isset($prizes['wheelId'])) {
            $nextWheelId = $prizes['wheelId'];
            unset($prizes['wheelId']);
        } else {
            $nextWheelId = '';
        }

        return array(
            'pos' => $pos,
            'prizes' => $prizes,
            'nextWheelId' => $nextWheelId
        );
    }

    /**
     * 计算奖励汇总
     * @param $machineObj SlotsMachine
     */
    public function getPrizes($hitItems, $machineId, $machineObj, $addition = 0)
    {
        $prizes = array();

        $uid = Bll::session()->get('uid');
        $gameInfo = $machineObj ? $machineObj->getGameInfo() : null;

        foreach ($hitItems as $item) {
            $type = strtoupper($item['itemType']);
            $value = $item['itemValue'];
            switch ($type) {
                case "COINS": //金币
                    $prizes[ITEM_COINS] += (int)$value;
                    break;
                case "FEATURE": //Feature
                    $prizes[ITEM_FEATURE] = $value;
                    break;
                case "FREE_GAME": //freespin
                    if ($machineId) {
                        if (!$machineObj) {
                            $machineObj = Bll::machine()->getMachineInstance($uid, $machineId, $gameInfo);
                        }
                        $prizes[ITEM_FREE_SPIN]['featureId'] = $machineObj->getFeatureByName(FEATURE_FREE_SPIN);
                    }
                    $prizes[ITEM_FREE_SPIN]['times'] += (int)$value;
                    break;
                case "MULTIPLE": //机台中当前Bet值的倍数(金币)
                    if (!$gameInfo) {
                        $gameInfo = Bll::game()->getGameInfo($uid, $machineId);
                    }
                    Log::info([$value, $gameInfo], 'wheel.log');
                    $prizes[ITEM_COINS] += (int)bcmul($gameInfo['totalBet'], $value);
                    break;
                case "AVGMULTIPLE": //机台中Bet均值的倍数
                    if (!$gameInfo) {
                        $gameInfo = Bll::game()->getGameInfo($uid, $machineId);
                    }
                    $avgBet = floor($gameInfo['avgBet'] / 1000) * 1000;
                    $prizes[ITEM_COINS] += (int)bcmul($avgBet, $value);
                    break;
                case "COLLECT_AVG_MULTIPLE": //机台收集转盘中Bet均值的倍数
                    if (!$gameInfo) {
                        $gameInfo = Bll::game()->getGameInfo($uid, $machineId);
                    }
                    $avgBet = floor($gameInfo['collectAvgBet'] / 1000) * 1000;
                    $prizes[ITEM_COINS] += (int)bcmul($avgBet, $value);
                    break;
                case "JACKPOT": //中Jackpot
                    $jackpotName = $value;
                    if ($machineId) {
                        if (!$machineObj) {
                            $machineObj = Bll::machine()->getMachineInstance($uid, $machineId, $gameInfo);
                        }
                        $pot = $machineObj->getJackpotAward($jackpotName, $jackpotId);

                        $prizes[ITEM_JACKPOT] = array(
                            'jackpotId' => $jackpotId,
                            'jackpotName' => $jackpotName,
                            'coins' => $pot
                        );
                    }

                    break;
                default:
                    if ($item['itemId']) { //物品奖励
                        $prizes[$item['itemId']] = $value;
                    }
                    break;
            }
        }

        if ($addition > 0) {
            foreach ($prizes as $key => $val) {
                if (!is_numeric($val)) continue;
                $prizes[$key] += (int)bcmul($val, $addition);
            }
        }

        return $prizes;
    }

    /**
     * @param $uid
     * @param $items
     * @param $machineId
     * @param null|\FF\Machines\SlotsModel\MachineCollectionTrait $machineObj
     */
    public function checkItems($uid, &$items, $machineId, $machineObj = null)
    {
        $gameInfo = [];
        foreach ($items as &$item) {
            $type = strtoupper($item['itemType']);
            switch ($type) {
                case "COLLECT_AVG_MULTIPLE": //机台收集转盘中Bet均值的倍数
                    if (!$gameInfo) {
                        if ($machineObj) {
                            $gameInfo = $machineObj->getCollectGameInfo();
                        } else {
                            $gameInfo = Bll::game()->getGameInfo($uid, $machineId, 'general');
                        }
                    }
                    $avgBet = $gameInfo['collectAvgBet'] ?? $gameInfo['avgBet'];
                    $avgBet = floor($avgBet / 1000) * 1000;
                    $item['count'] = (int)bcmul($avgBet, $item['itemValue']);
                    break;
                default :
                    $item['count'] = $item['itemValue'];
                    break;
            }
        }
    }

    /**
     * 计算期望值
     * @param $machineId
     */
    public function calcExpectedValue($wheelId, $excludePoses = array())
    {
        $wheelItems = $this->getWheelItems($wheelId);
        $totalWeight = 0;
        $value = 0;
        foreach ($wheelItems as $wheelItem) {
            if (in_array($wheelItem['pos'], $excludePoses)) continue;
            $totalWeight += $wheelItem['weight'];
            $value += $wheelItem['itemValue'] * $wheelItem['weight'];
        }

        return bcdiv($value, $totalWeight, 5);
    }
}