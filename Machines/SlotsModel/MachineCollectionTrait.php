<?php
/**
 * 机台收集
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Factory\Feature;
use FF\Library\Utils\Utils;
use GPBClass\Enum\MSG_ID;


trait MachineCollectionTrait
{
    protected $collectGameInfo = array();
    protected $collectGameUpdated = false;

    public function clearBuffer()
    {
        parent::clearBuffer();

        $this->collectGameUpdated = false;
    }

    /**
     * CollectGame 收集
     */
    public function getCollectGameInfo()
    {
        if ($this->collectGameInfo) {
            return $this->collectGameInfo;
        }

        $collectGameInfo = Bll::machineCollectGame()->getCollectInfo(
            $this->uid, $this->machineId, $this->gameInfo
        );

        $betIndex = ceil($collectGameInfo['unlockBetLevel'] * count($this->betOptions) / 100);
        $collectGameInfo['unlockBet'] = $this->getTotalBetByIndex($betIndex);

        $this->gameInfo['collectNode'] = $collectGameInfo['node'];
        $this->gameInfo['collectTarget'] = $collectGameInfo['target'];
        $this->gameInfo['collectProgress'] = $collectGameInfo['progress'];
        $this->gameInfo['collectAvgBet'] = $collectGameInfo['avgBet'];

        $this->collectGameInfo = $collectGameInfo;

        return $collectGameInfo;
    }

    public function collectGameAward($awardCfg = [])
    {
        $collectInfo = $this->getCollectGameInfo();

        //当前节点序号与配置
        $node = $collectInfo['node'];
        $featureId = $this->getFeatureByName(FEATURE_COLLECT_GAME);
        $featureCfg = $this->getFeatureConfig($featureId);
        $awardCfg = $awardCfg ? [$node => $awardCfg] : $featureCfg['itemAwardLimit']['nodesAward'];

        // 节点奖励
        $avgBet = $collectInfo['avgBet'] ?: $this->getTotalBet();
        $betSummary = $collectInfo['betSummary'];
        $collectValue = $collectInfo['value'];

        $prizes = $this->getCollectGamePrizes($node, $awardCfg[$node], $avgBet, $betSummary, $collectValue);

        // 测试统计
        if (defined('TEST_ID')) {
            Bll::slotsTest()->featureStats("CollectGame SpinTimes", "CollectGameSpinTimes>Node:{$node}", array("collected" => $this->getGameInfo('collectSpinTimes')));
        }

        //设置下个收集节点，并重置进度
        $newNode = $node + 1;
        if (!isset($awardCfg[$newNode])) $newNode = 1;
        $this->updateCollectGameNode($newNode, true);

        return array(
            'prizes' => $prizes,
            'collectInfo' => $this->getCollectGameInfo()
        );
    }

    public function updateAdditions($prizes)
    {
        parent::updateAdditions($prizes);

        // 更新收集进度
        $this->updateCollectGameProgress();
    }

    protected function updateCollectGameProgress()
    {
        $totalBet = $this->getTotalBet();
        $collectInfo = $this->getCollectGameInfo();

        //在FreeGame中并且FreeGame中不进行收集，则不累计进度
        if ($this->isFreeSpin() && !$collectInfo['inFreeSpin']) {
            return;
        }

        //已完成收集或者未解锁，则不累计进度
        if ($collectInfo['complete'] || $totalBet < $collectInfo['unlockBet']) {
            return;
        }

        $collectUpdates = array();

        //计算新获得的收集进度
        $progressAdd = $this->getCollectGameProgressAdd();
        if ($progressAdd) {
            $progress = $collectInfo['progress'] + $progressAdd;
            if ($progress > $collectInfo['target']) {
                $progress = $collectInfo['target'];
            }
            $collectUpdates['progress'] = $progress;
        }

        //更新收集期间的下注次数、下注总额、下注均值、收集值
        $spinTimes = $collectInfo['spinTimes'] + 1;
        $betSummary = $collectInfo['betSummary'] + $totalBet;
        $collectUpdates['spinTimes'] = $spinTimes;
        $collectUpdates['betSummary'] = $betSummary;
        $collectUpdates['avgBet'] = floor(($betSummary / $spinTimes) / 1000) * 1000;

        $this->updateCollectGameInfo($collectUpdates);

        if ($progressAdd > 0) {
            $this->collectGameUpdated = true;
        }
    }

    public function getCollectGameProgressAdd($elements = null)
    {
        $progressAdd = 0;
        $collectInfo = $this->getCollectGameInfo();
        $collectType = $collectInfo['collectType'];

        if (isset($this->runOptions['sampleLibId']) && $this->runOptions['sampleLibId'] === 'CollectGame') {
            return $collectInfo['target'] - $collectInfo['progress'];
        }

        switch ($collectType) {
            case 'TotalBet':
                $progressAdd = $this->betContext['cost'];
                break;
            case 'MachineItem':
                $elements = $elements ?: $this->getStepElements();
                $collectItems = array_flip($collectInfo['collectItems']);
                foreach ($elements as $element) {
                    $col = $element['col'];
                    $row = $element['row'];
                    //被掉落元素替换的元素不计数
                    if (isset($this->replacedElements[$col][$row])) {
                        continue;
                    }
                    if (isset($collectItems[$element['elementId']])) {
                        $progressAdd++;
                    }
                }
                break;
            default:
                break;
        }

        return $progressAdd;
    }

    public function updateCollectGameInfo($data)
    {
        foreach ($data as $key => $value) {
            $this->collectGameInfo[$key] = $value;
            $gameInfoKey = 'collect' . ucfirst($key);
            if (isset($this->gameInfo[$gameInfoKey])) {
                $this->gameInfo[$gameInfoKey] = $value;
            }
        }

        //刷新收集完成状态
        if (isset($data['progress'])) {
            $this->collectGameInfo['complete'] = $data['progress'] >= $this->collectGameInfo['target'];
        }
    }

    /**
     * spin结束时附加其它功能消息
     */
    protected function getAdditionMessages($prizes)
    {
        $messages = parent::getAdditionMessages($prizes);

        //机台收集进度通知
        if ($this->collectGameUpdated) {
            $messages[MSG_ID::MSG_NTF_COLLECT_PROGRESS] = array(
                'collectProgress' => $this->gameInfo['collectProgress'],
                'collectAvgBet' => $this->gameInfo['collectAvgBet'],
                'collectValue' => $this->gameInfo['collectValue']
            );
        }
        return $messages;
    }

    public function clearCollectGameAvgBet()
    {
        $this->updateCollectGameInfo(array(
            'spinTimes' => 0,
            'betSummary' => 0,
            'avgBet' => 0
        ));
    }

    /**
     * 检查、校正feature奖励
     */
    protected function checkFeaturePrizes(&$features, &$featurePrizes, $elements)
    {
        $result = parent::checkFeaturePrizes($features, $featurePrizes, $elements);

        $totalBet = $this->getTotalBet();
        $collectInfo = $this->getCollectGameInfo();

        // 在FreeGame中并且FreeGame中不进行收集，则不累计进度
        if ($this->isFreeSpin() && !$collectInfo['inFreeSpin']) {
            return $result;
        }

        // 未解锁，则不累计进度
        if ($totalBet < $collectInfo['unlockBet']) {
            return $result;
        }

        // 收集数量计数，完成则不累计进度
        $progressAdd = 0;
        if (!$collectInfo['complete']) {
            $elementsList = $this->elementsToList($elements);
            $progressAdd = $this->getCollectGameProgressAdd($elementsList);
        }

        // 检查是否触发 Feature
        $progress = $collectInfo['progress'] + $progressAdd;
        if ($progress >= $collectInfo['target']) {
            $features[] = $this->getFeatureByName(FEATURE_COLLECT_GAME);
        }

        return $result;
    }

    public function updateCollectGameNode($node, $resetSpins)
    {
        $updates = array(
            'collectNode' => $node,
            'collectTarget' => 0,
            'collectProgress' => 0
        );

        if ($node == 1 || $resetSpins) {
            $updates['collectSpinTimes'] = 0;
            $updates['collectBetSummary'] = 0;
            $updates['collectAvgBet'] = 0;
            $updates['collectValue'] = 0;
        }

        $this->updateGameInfo($updates);

        $this->collectGameInfo = array();
    }

    /**
     * 领取收集节点奖励
     */
    public function getCollectGamePrizes($node, $rewardOptions, $avgBet, $betSummary, $collectValue)
    {
        $prizes = array();
        switch ($rewardOptions['type']) {
            case 'coins':
                $coins = $avgBet * $rewardOptions['value'];
                $prizes[ITEM_COINS] = Utils::valueFormat($coins);
                break;
            case 'freegames':
                $featureId = $this->getFeatureByName(FEATURE_FREE_SPIN);
                if (isset($rewardOptions['featureId'])) {
                    $featureId = $rewardOptions['featureId'];
                }
                $prizes[ITEM_FREE_SPIN]['featureId'] = $featureId;
                $prizes[ITEM_FREE_SPIN]['times'] += (int)$rewardOptions['value'];
                break;
            case 'wheel':
                $prizes[ITEM_WHEEL] = array(
                    'wheelId' => $rewardOptions['value'],
                    'hitResult' => Bll::wheel()->getHitResult($rewardOptions['value'], $this)
                );
                break;
        }

        return $prizes;
    }

    /**
     * 获取收集节点信息
     */
    public function getCollectGameNodes()
    {
        $featureId = $this->getFeatureByName(FEATURE_COLLECT_GAME);
        $featureCfg = $this->getFeatureConfig($featureId);
        $awardCfg = $featureCfg['itemAwardLimit']['nodesAward'];

        $nodesInfo = array();
        foreach ($awardCfg as $nodeId => $nodeVal) {
            $nodeVal['nodeId'] = $nodeId;
            $nodesInfo[] = $nodeVal;
        }

        return $nodesInfo;
    }

    public function getNodeRewardInfo()
    {
        $nodes = $this->getCollectGameNodes();
        $nodes = array_column($nodes, null, 'nodeId');
        $nodeInfo = $this->getCollectGameInfo();

        return $nodes[$nodeInfo['node']] ?? [];
    }

    public function onFeatureTriggered($featureId)
    {
        if ($this->getFeatureName($featureId) == FEATURE_COLLECT_GAME && $this->getGameInfo('featureId') !== $featureId) {
            $this->getFeaturePlugin($featureId)->onTrigger();
        }

        parent::onFeatureTriggered($featureId);
    }

    public function getFeaturePlugin($featureId)
    {
        $featureName = $this->getFeatureName($featureId);

        if ($featureName === FEATURE_COLLECT_GAME) {
            return Feature::collectGame($this, $featureId);
        }

        return parent::getFeaturePlugin($featureId);
    }

}