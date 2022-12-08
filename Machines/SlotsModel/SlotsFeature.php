<?php
/**
 * 老虎机Feature逻辑
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Factory\Feature;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;
use FF\Machines\Features\BaseFeature;
use FF\Service\Lib\Service;

abstract class SlotsFeature extends SlotsJackpot
{
    protected $featureData = array();
    protected $featureWinInfo = array();
    protected $replacedElements = array();
    protected $featureOptionsInit = false;

    public function clearBuffer()
    {
        parent::clearBuffer();

        $this->featureData = array();
        $this->featureWinInfo = array();
        $this->replacedElements = array();
        $this->featureOptionsInit = false;
    }

    public function getFeatureName($featureId)
    {
        if (!$featureId || !isset($this->featureGames[$featureId])) {
            return '';
        }

        return $this->featureGames[$featureId]['featureName'];
    }

    public function getFeatureConfig($featureId)
    {
        return $this->featureGames[$featureId];
    }

    public function getFeatureNames($featureIds)
    {
        $featureNames = array();
        if (!$featureIds) return $featureNames;

        foreach ($featureIds as $featureId) {
            $featureNames[] = $this->getFeatureName($featureId);
        }

        return $featureNames;
    }

    public function getFeatureByName($featureName)
    {
        foreach ($this->featureGames as $featureId => $feature) {
            if ($feature['featureName'] === $featureName) {
                return $featureId;
            }
        }

        return '';
    }

    public function getFeaturesByName($featureName)
    {
        $features = array();

        foreach ($this->featureGames as $featureId => $feature) {
            if ($feature['featureName'] === $featureName) {
                $features[] = $featureId;
            }
        }

        return $features;
    }

    /**
     * 当前feature
     */
    public function getCurrFeature()
    {
        if (!$this->isSpinning) {
            return $this->gameInfo['featureId'];
        }

        $currFeature = $this->betContext['feature'];

        if (!$currFeature && !empty($this->betContext['preFeatures'])) {
            $currFeature = $this->getActivatedFeature($this->betContext['preFeatures']);
        }

        return $currFeature;
    }

    /**
     * 获取备份feature列表
     */
    public function getBakFeatures()
    {
        if ($this->gameInfo['bakFeatures']) {
            return array_column($this->gameInfo['bakFeatures'], 'featureId');
        } else {
            return [];
        }
    }

    /**
     * 初始化feature选项
     */
    public function initFeatureOptions()
    {
        if ($this->featureOptionsInit) {
            return;
        }

        $this->featureOptionsInit = true;
        $this->featureGames = $this->featureGamesBak;
    }

    /**
     * 获取feature触发控制选项
     */
    public function getTriggerOptions($featureId)
    {
        $this->initFeatureOptions();

        return $this->featureGames[$featureId]['triggerOptions'];
    }

    /**
     * 获得触发feature所需元素最小个数
     */
    public function getTriggerElementCount($elementId)
    {
        $triggerCount = 0;

        foreach ($this->featureGames as $featureGame) {
            if ($featureGame['triggerItems'] == $elementId) {
                $count = $featureGame['triggerItemNum'];
                if (substr($count, -1) == '+') {
                    $count = substr($count, 0, -1);
                }
                if ($triggerCount) {
                    $triggerCount = min($triggerCount, (int)$count);
                } else {
                    $triggerCount = (int)$count;
                }
            }
        }

        return $triggerCount;
    }

    /**
     * 获得可触发的feature集合
     */
    public function getTriggerAbleFeatures()
    {
        if (isset($this->runOptions['featureTriggerAble'])) {
            if ($this->runOptions['featureTriggerAble']) {
                $triggerAbleFeatures = array_flip($this->runOptions['featureTriggerAble']);
            } else {
                $triggerAbleFeatures = array();
            }
        } else {
            $triggerAbleFeatures = array_flip(array_keys($this->featureGames));
            //多版本兼容
            foreach ($triggerAbleFeatures as $featureId => $v) {
                if ($this->featureGames[$featureId]['version'] && !$this->isVirtualMode) {
                    $version = Bll::session()->get('version');
                    if (!Bll::version()->isMatch($version, $this->featureGames[$featureId]['version'])) {
                        unset($triggerAbleFeatures[$featureId]);
                    }
                }
            }
        }

        return $triggerAbleFeatures;
    }

    /**
     * 获得前置触发的feature
     * 先出feature，再分配机台元素
     */
    public function getPreTriggerFeature()
    {
        if (isset($this->runOptions['noFeature'])) {
            if ($this->runOptions['noFeature']) return array();
        }

        $features = array();
        $currFeature = $this->betContext['feature'];
        $triggerAbleFeatures = $this->getTriggerAbleFeatures();

        foreach ($this->featureGames as $featureId => $feature) {
            $triggerOptions = $this->getTriggerOptions($featureId);
            if (empty($triggerOptions['preTrigger'])) continue;
            if (!isset($triggerAbleFeatures[$featureId])) continue;
            if (!$this->isTriggerAbleInFeature($featureId, $currFeature, $features)) continue;
            if (!empty($this->runOptions['features'])) {
                foreach ($this->runOptions['features'] as $featureReg) {
                    if ($this->isFeatureMatched($featureReg, $featureId)) {
                        if (!empty($triggerOptions['withFeature'])) {
                            $features[] = $triggerOptions['withFeature'];
                        }
                        $features[] = $featureId;
                    }
                }
                continue;
            }
            if (!empty($triggerOptions['ratio']) && $this->revisionTriggerRatio($featureId, $triggerOptions['ratio'])) {
                $features[] = $featureId;
            }
        }

        return $features;
    }

    /**
     * 获得触发的feature
     */
    public function getTriggerFeatures($hitResultIds, $elements)
    {
        $features = array();

        //带元素消除玩法的机台，消除过程中不触发任何feature
        if ($this->step > 1 && $this->hasEliminate) {
            return $features;
        }

        //预触发的feature，只有第一步有效
        if ($this->betContext['preFeatures'] && $this->step == 1) {
            $features = $this->betContext['preFeatures'];
        }

        $currFeature = $this->betContext['feature'];
        $triggerAbleFeatures = $this->getTriggerAbleFeatures();

        foreach ($this->featureGames as $featureId => $feature) {
            $triggerOptions = $feature['triggerOptions'];
            if (!empty($triggerOptions['preTrigger'])) continue;
            if (!isset($triggerAbleFeatures[$featureId])) continue;
            if (!$this->isTriggerAbleInFeature($featureId, $currFeature, $features)) continue;
            if ($feature['triggerItems']) { //按指定元素触发
                if ($feature['triggerOnline']) { //必须在中奖线上触发
                    $triggerLines = $feature['triggerLines'];
                    foreach ($hitResultIds as $lineId => $resultId) {
                        $_elements = $this->getElementsOnline($lineId, $elements, true);
                        if (!in_array('*', $triggerLines) && !in_array($lineId, $triggerLines)) continue;
                        if ($this->triggerByElements($featureId, $_elements)) {
                            $features[] = $featureId;
                        }
                    }
                } elseif ($triggerOptions['triggerOnRow']) { // 触发元素必须是同一行
                    for ($row = 1; $row <= $this->machine['rows']; $row++) {
                        $_elements = $this->getElementsByRow($row, $elements);
                        if ($this->triggerByElements($featureId, $_elements)) {
                            $features[] = $featureId;
                        }
                    }
                } elseif ($triggerOptions['limitOnRow']) { // 触发元素必须在某一行
                    $_elements = $this->getElementsByRow($triggerOptions['limitOnRow'], $elements);
                    if ($this->triggerByElements($featureId, $_elements)) {
                        $features[] = $featureId;
                    }
                } else {
                    if ($this->triggerByElements($featureId, $elements)) {
                        $features[] = $featureId;
                    }
                }
            } elseif (isset($triggerOptions['ratio'])) { //按固定概率触发
                //支持中奖和不中奖时触发的概率不同
                if (is_array($triggerOptions['ratio'])) {
                    $key = $hitResultIds ? 'win' : 'notWin';
                    $triggerOptions['ratio'] = $triggerOptions['ratio'][$key];
                }
                //ratio=0时，普通spin中不能触发此feature，只能在指定触发此feature时才可以
                if ($triggerOptions['ratio'] == 0 && !empty($this->runOptions['features'])) {
                    $_features = $this->runOptions['features'];
                    $featureName = $feature['featureName'];
                    if (in_array($featureId, $_features) || in_array($featureName, $_features)) {
                        $features[] = $featureId;
                    }
                } elseif (empty($this->runOptions['noFeature']) && Utils::isHitByRate($triggerOptions['ratio'])) {
                    $features[] = $featureId;
                }
            }
        }

        if ($features) {
            $features = $this->makeFeatureUniqueness($features);
        }

        return $features;
    }

    /**
     * 判断某feature是否能在当前feature下触发
     */
    public function isTriggerAbleInFeature($featureId, $currFeature, $features)
    {
        $feature = $this->featureGames[$featureId];
        $triggerOptions = $feature['triggerOptions'];

        //检查是否只能在特定feature中触发
        if (!empty($triggerOptions['inFeature'])) {
            $featureLimits = explode(',', $triggerOptions['inFeature']);
            if (!$currFeature || !in_array($currFeature, $featureLimits)) {
                return false;
            }
            //限制触发次数
            if (isset($triggerOptions['maxTimes'])) {
                $maxTimes = $triggerOptions['maxTimes'];
                $triggerTimes = $this->getFeatureDetail('triggerTimes');
                if ($triggerTimes && isset($triggerTimes[$featureId]) && $triggerTimes[$featureId] >= $maxTimes) {
                    return false;
                }
            }
        }

        //检查是否不能在某些feature中触发
        if (!empty($triggerOptions['notInFeature']) && $currFeature) {
            $featureLimits = explode(',', $triggerOptions['notInFeature']);
            if (in_array($currFeature, $featureLimits)) return false;
            if (in_array('*', $featureLimits)) return false;
        }

        //检查是否必须跟随其他feature触发
        if (!empty($triggerOptions['withFeature'])) {
            $forceTrigger = false;
            $withFeature = $triggerOptions['withFeature'];
            if (!empty($this->runOptions['features'])) {
                foreach ($this->runOptions['features'] as $featureReg) {
                    if ($this->isFeatureMatched($featureReg, $featureId)) {
                        $forceTrigger = true;
                    }
                }
            }
            if (!$forceTrigger && !in_array($withFeature, $features)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查某feature是否触发（按指定元素）
     */
    protected function triggerByElements($featureId, $elements)
    {
        $feature = $this->featureGames[$featureId];
        $triggerItems = $feature['triggerItems'];
        $triggerItemNums = explode('|', $feature['triggerItemNum']);

        if (strpos($triggerItems, '|')) { //或模式，多个不同元素组合触发(不要求每个元素都出现)
            $triggerItems = explode('|', $triggerItems);
        } else { //常规模式，单一元素触发
            $triggerItems = [$triggerItems];
        }

        $elementsCount = $this->elementsCount($this->elementsToList($elements));
        $triggerItemCount = 0;
        foreach ($triggerItems as $triggerItem) {
            $triggerItemCount += $elementsCount[$triggerItem] ?? 0;
        }

        $triggered = false;

        foreach ($triggerItemNums as $triggerItemNum) {
            if (!Utils::isValueMatched($triggerItemCount, $triggerItemNum)) {
                continue;
            }

            if ($this->getFeatureAwardFreeSpin($featureId, $feature, $triggerItemCount)) {
                $triggered = true;
            }

            break;
        }

        if ($triggered) {
            $this->featureData[$featureId]['triggerItemCount'] = $triggerItemCount;
        }

        return $triggered;
    }

    /**
     * 校正已预触发的feature
     */
    protected function checkPreFeature()
    {
        //to override
    }

    /**
     * 检查、校正已触发的feature
     */
    protected function checkTriggeredFeatures(&$features, $hitResultIds, $elements)
    {
        sort($features, SORT_STRING);

        return true;
    }

    /**
     * 检查指定feature，保证必中或必不中
     */
    protected function checkForceTriggeredFeatures($features, $retry)
    {
        //必不中任何feature
        if (!empty($this->runOptions['noFeature']) && $features) {
            return false;
        }

        //必中指定feature
        if (!empty($this->runOptions['features'])) {
            $currFeature = $this->getCurrFeature();
            $triggerAbleFeatures = $this->getTriggerAbleFeatures();
            foreach ($this->runOptions['features'] as $featureReg) {
                $featureId = $this->getMatchedFeature($featureReg);
                if (!$featureId || !isset($triggerAbleFeatures[$featureId])) {
                    continue;
                }
                if (!$this->isTriggerAbleInFeature($featureId, $currFeature, $features)) {
                    continue;
                }
                if (!$this->getMatchedFeature($featureReg, $features)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 当同时触发多个同类feature时，进行唯一化处理
     */
    protected function makeFeatureUniqueness($features)
    {
        $featureGroups = array();
        foreach ($features as $featureId) {
            $featureName = $this->getFeatureName($featureId);
            if (!isset($featureGroups[$featureName])) $featureGroups[$featureName] = [];
            $featureGroups[$featureName][] = $featureId;
        }

        $features = array();
        foreach ($featureGroups as $featureGroup) {
            if (count($featureGroup) > 1) {
                $features[] = $this->getActivatedFeature($featureGroup);
            } else {
                $features[] = $featureGroup[0];
            }
        }

        return $features;
    }

    /**
     * 当同时触发多个feature时，按照优先级决出当前激活的feature
     */
    public function getActivatedFeature($features, $featureId = '')
    {
        if (!$features) return $featureId;

        foreach ($features as $_featureId) {
            if ($featureId) {
                if ($this->featureGames[$_featureId]['priority'] > $this->featureGames[$featureId]['priority']) {
                    $featureId = $_featureId;
                }
            } else {
                $featureId = $_featureId;
            }
        }

        return $featureId;
    }

    /**
     * 当同时触发多个feature时，找出其中的FreeGame
     */
    public function getTriggeredFreeGame($features)
    {
        foreach ($features as $featureId) {
            if ($this->isFreeGame($featureId)) {
                return $featureId;
            }
        }

        return null;
    }

    /**
     * 初始化feature奖励
     */
    protected function initFeaturePrizes()
    {
        $multiple = 1;
        $featureId = $this->gameInfo['featureId'];
        if ($featureId) {
            $multiple = max(1, $this->featureGames[$featureId]['multipleAward']);
        }

        return array(
            'coins' => 0,
            'freespin' => 0,
            'multiple' => $multiple, //全体倍数奖励
            'multiples' => array(), //中奖线倍数奖励
            'elements' => array(), //元素掉落奖励
            'splitElements' => array(), //按feature隔离的元素掉落奖励
            'values' => array(), //元素上的附属数值
        );
    }

    /**
     * 合并feature奖励
     */
    protected function mergeFeaturePrizes(&$prizes, $otherPrizes)
    {
        foreach ($prizes as $key => $val) {
            if (!empty($otherPrizes[$key])) {
                $newVal = $otherPrizes[$key];
                if (in_array($key, ['coins', 'freespin'])) {
                    $newVal += $val;
                } elseif (in_array($key, ['multiple'])) {
                    $newVal *= $val;
                }
                $prizes[$key] = $newVal;
            }
        }
    }

    /**
     * 获取feature奖励
     */
    public function getFeaturePrizes(&$features, &$hitResultIds, $elements)
    {
        $prizes = $this->initFeaturePrizes();

        if (!$features) return $prizes;

        //根据feature配置给予各项奖励
        foreach ($features as $k => $featureId) {
            $feature = $this->featureGames[$featureId];
            $coins = $feature['coinsAward'];
            //触发feature的奖励数量，可能根据触发元素的数量而不同
            if (is_array($coins)) {
                $itemCount = $this->featureData[$featureId]['triggerItemCount'];
                $coins = $coins[$itemCount];
            }
            $freespin = $this->getFeatureAwardFreeSpin($featureId, $feature);
            $prizes['coins'] += $coins * $this->getBetMultiple();
            $prizes['freespin'] += $freespin;
            $prizes['multiple'] *= max(1, $feature['multipleAward']);
            if ($feature['itemAward']) {
                $dropElements = $this->dropElementsInFeature($featureId, $elements);
                if ($dropElements) {
                    $prizes['elements'] = $this->elementsMerge($prizes['elements'], $dropElements);
                    $prizes['splitElements'][$featureId] = $dropElements;
                } else {
                    unset($features[$k]);
                }
            }
        }

        //获得了元素奖励，重新计算中奖结果
        if ($prizes['elements'] && $this->isRecalculateByDropElements()) {
            $elements = $this->elementsMerge($elements, $prizes['elements']);
            $elementsList = $this->elementsToList($prizes['elements']);
            $values = $this->getElementsValue($elementsList, $features);
            $hitResultIds = $this->getHitResultIds($elements, $values);
            if ($values && $this->paylines) {
                $prizes['multiples'] = $this->getLineMultiples($hitResultIds, $elements, $values);
            }
            $this->replacedElements = $prizes['elements'];
            if (count($prizes['splitElements']) == 1) {
                $prizes['splitElements'] = array();
            }
        }

        //处理其他特殊feature奖励
        foreach ($features as $k => $featureId) {
            $featureName = $this->getFeatureName($featureId);
            if ($featureName == FEATURE_WILD_MULTI) {
                $otherPrizes = $this->getWildMultiPrizes($featureId, $hitResultIds, $elements, $prizes);
                if ($otherPrizes) $this->mergeFeaturePrizes($prizes, $otherPrizes);
                if (!empty($otherPrizes['values'])) {
                    if (!$this->paylines && $this->isRecalculateByWildMulti()) { //全线机台触发了wild翻倍时，需要重算中奖结果
                        $hitResultIds = $this->getHitResultIds($elements, $otherPrizes['values']);
                    }
                } else {
                    unset($features[$k]);
                }
            } else {
                $otherPrizes = $this->getOtherFeaturePrizes($featureId, $hitResultIds, $elements);
                if ($otherPrizes) $this->mergeFeaturePrizes($prizes, $otherPrizes);
            }
        }

        $features = array_values($features);

        return $prizes;
    }

    /**
     * 计算Wild翻倍奖励
     */
    public function getWildMultiPrizes($featureId, $hitResultIds, $elements, $featurePrizes)
    {
        $prizes = array();
        $prizes['values'] = array();

        $triggerOptions = $this->getTriggerOptions($featureId);
        $awardLimit = $this->featureGames[$featureId]['itemAwardLimit'];
        $multiRatios = $triggerOptions['multiRatios'] ?: $awardLimit['multiRatios'];
        $limitType = $awardLimit['limitType'] ?: '';
        $multiItems = array();
        $colMulti = array();

        if ($limitType == 'reel') {
            $cols = array();
            $reelRatios = $awardLimit['reelRatios'];
            $reelNum = (int)Utils::randByRates($awardLimit['numReelRatios']);
            //当同时触发了掉落wild的feature，则wild翻倍只能出现在掉落的wild上
            if (!empty($featurePrizes['elements'])) {
                foreach ($reelRatios as $col => $weight) {
                    if (!isset($featurePrizes['elements'][$col])) {
                        unset($reelRatios[$col]);
                    }
                }
            }
            for ($i = 0; $i < $reelNum; $i++) {
                if (!$reelRatios) break;
                $col = (int)Utils::randByRates($reelRatios);
                unset($reelRatios[$col]);
                $cols[] = $col;
            }
            sort($cols);
        } else {
            $cols = array_keys($multiRatios);
        }

        //按照既定策略筛选出翻倍Wild元素
        //部分wild翻倍feature特殊，要求整列wild都翻倍，并且倍数一样
        foreach ($cols as $col) {
            foreach ($elements[$col] as $row => $elementId) {
                if (!$this->isWildElement($elementId)) continue;
                if (is_array($multiRatios[$col])) { //非固定倍数，按权重进行随机
                    if ($limitType == 'reel' && isset($colMulti[$col])) {
                        $multiple = $colMulti[$col];
                    } else {
                        $multiple = (int)Utils::randByRates($multiRatios[$col]);
                        if ($limitType == 'reel') {
                            $colMulti[$col] = $multiple;
                        }
                    }
                } elseif (is_numeric($multiRatios[$col])) { //固定倍数
                    $multiple = (int)$multiRatios[$col];
                } else {
                    break;
                }
                if ($multiple <= 1) continue;
                $multiItems[] = array(
                    'col' => $col, 'row' => $row, 'value' => (string)$multiple
                );
            }
        }

        //Wild翻倍个数限制
        if (!empty($triggerOptions['maxNum'])) {
            if (count($multiItems) > $triggerOptions['maxNum']) {
                shuffle($multiItems);
                $multiItems = array_slice($multiItems, 0, $triggerOptions['maxNum']);
            }
        }

        foreach ($multiItems as $item) {
            $prizes['values'][$item['col']][$item['row']] = $item['value'];
        }

        if ($this->paylines) {
            $prizes['multiples'] = $this->getLineMultiples($hitResultIds, $elements, $prizes['values']);
        }

        return $prizes;
    }

    /**
     * 计算中奖线的奖励翻倍倍数
     */
    public function getLineMultiples($hitResultIds, $elements, $values)
    {
        $multiples = array();

        foreach ($hitResultIds as $lineId => $resultId) {
            $routes = $this->paylines[$lineId]['route'];
            $payElements = $this->paytable[$resultId]['elements'];
            foreach ($values as $col => $_values) {
                $row = $routes[$col - 1];
                if (!$payElements[$col - 1]) continue;
                if (!isset($_values[$row])) continue;
                if (!$this->isWildElement($elements[$col][$row])) continue;
                $multiple = (int)$_values[$row];
                if (isset($multiples[$lineId])) {
                    $multiples[$lineId] *= $multiple;
                } else {
                    $multiples[$lineId] = $multiple;
                }
            }
        }

        return $multiples;
    }

    /**
     * 获取其他特定feature奖励
     */
    public function getOtherFeaturePrizes($featureId, $hitResultIds, $elements)
    {
        //to override
        return array();
    }

    /**
     * 检查、校正feature奖励
     */
    protected function checkFeaturePrizes(&$features, &$featurePrizes, $elements)
    {
        sort($features, SORT_STRING);
        //to override
        return true;
    }

    /**
     * 测试模式下，部分Feature自动完成，并且获得奖励
     */
    protected function autoPlayFeatureInTesting(&$features, &$featurePrizes)
    {
        $index = 0;
        $features = array_values(array_filter($features));

        while ($index < count($features)) {
            $featureId = $features[$index];
            $prizes = $this->getFeaturePrizesAutoInTesting($featureId);
            if (!empty($prizes['coins'])) $featurePrizes['coins'] += $prizes['coins'];
            if (!empty($prizes['freespin'])) $featurePrizes['freespin'] += $prizes['freespin'];
            if (!empty($prizes['feature'])) $features[] = $prizes['feature'];
            if (!empty($prizes['coins'])) {
                $this->featureWinInfo[$featureId] = $prizes['coins'];
            }

            $index++;
        }
    }

    /**
     * 自动完成feature，获得奖励(测试模式下)
     */
    public function getFeaturePrizesAutoInTesting($featureId, $args = [])
    {
        $prizes = array();

        return $prizes;
    }

    /**
     * 处理已触发的feature
     */
    public function dealWithTriggeredFeature($features, $freespin = 0)
    {
        //触发FreeGame处理
        $featureId = $this->getTriggeredFreeGame($features);
        if ($featureId) {
            if ($this->isVirtualMode && !$freespin) {
                $freespin = $this->getFreespinInitTimes($featureId);
            }
            $this->onFreeGameTriggered($featureId, $freespin);
        }

        //最后一次freespin处理
        if ($this->betContext['isLastFreeSpin']) {
            if (!$features || !$this->willBreakSpin($features) || $this->isVirtualMode) {
                $this->onFreespinOver();
            }
        }

        //触发feature时，进行feature数据初始化
        foreach ($features as $featureId) {
            //累计机台内该feature的触发次数
            $featureName = $this->getFeatureName($featureId);
            $this->gameInfo['featureTimes'][$featureName]++;
            //先清除可能存在的feature实例，防止受之前触发的feature数据影响
            Feature::clearInstanceByFeature($this->instanceId, $featureId);
            $this->onFeatureTriggered($featureId);
        }

        //当未触发特定feature时，进行feature处理
        $this->onFeatureNonTriggered($features);
    }

    /**
     * 当触发feature时的逻辑
     */
    public function onFeatureTriggered($featureId)
    {
        $triggerOptions = $this->getTriggerOptions($featureId);

        //触发次数记录
        if (isset($triggerOptions['maxTimes'])) {
            $triggerTimes = $this->getFeatureDetail('triggerTimes');
            if (!$triggerTimes) $triggerTimes = array();
            $triggerTimes[$featureId]++;
            $this->setFeatureDetail(array('triggerTimes' => $triggerTimes), false);
        }
    }

    /**
     * 当未触发特定feature时的逻辑
     */
    public function onFeatureNonTriggered($features)
    {
        //to override
    }

    /**
     * 当feature结束时的逻辑
     */
    public function onFeatureEnd($featureId, &$settled = null)
    {
        //若feature栈内还有其它feature未完成，则弹出一个置为当前feature
        $bakFeatures = $this->gameInfo['bakFeatures'];
        if ($bakFeatures) {
            $featureInfo = array_pop($bakFeatures);
            $this->betContext['feature'] = $featureInfo['featureId'];
            $this->betContext['featureNo'] = $featureInfo['featureNo'];
            $this->updateGameInfo(array(
                'featureId' => $featureInfo['featureId'],
                'featureNo' => $featureInfo['featureNo'],
                'featureDetail' => $featureInfo['featureDetail'],
                'bakFeatures' => $bakFeatures,
            ));
            //若仍然处于freespin中，则此feature不结算，等FreeGame结束后一起结算
            if ($this->isInFreeGame()) {
                $settled = false;
            }
        } else {
            $this->clearFeature();
        }
    }

    /**
     * 查找与指定feature匹配的featureId
     */
    public function getMatchedFeature($featureReg, $features = null)
    {
        $matched = '';
        if ($features === null) {
            $features = array_keys($this->featureGames);
        }

        foreach ($features as $featureId) {
            if ($this->isFeatureMatched($featureReg, $featureId)) {
                $matched = $featureId;
                break;
            }
        }

        return $matched;
    }

    /**
     * 检查featureId是否与给定feature表达式匹配
     * feature表达式中支持featureID/feature名称，支持末尾通配符*
     */
    public function isFeatureMatched($featureReg, $featureId)
    {
        $featureReg = str_replace('*', '', $featureReg);

        if ($featureReg == $featureId) return true;
        if (strpos($featureId, $featureReg) !== false) return true;

        $featureName = $this->getFeatureName($featureId);
        if ($featureReg == $featureName) return true;

        return false;
    }

    /**
     * 初始化feature所用的下注额
     */
    public function initFeatureTotalBet()
    {
        return $this->getTotalBet();
    }

    /**
     * 当触发FreeGame时的逻辑
     */
    public function onFreeGameTriggered($featureId, $times, $featureDetail = array())
    {
        Log::info("onFreeGameTriggered, featureId = {$featureId}, times = {$times}", 'slotsGame.log');

        $totalBet = $this->initFeatureTotalBet();

        $this->setFeature($featureId, $featureDetail, $totalBet);

        if (!$this->betContext['isFreeSpin']) {
            if ($times) {
                $this->initFreespin($times);
            }
        } else {
            $this->betContext['isLastFreeSpin'] = false;
            if ($times) {
                $this->addFreespinTimes($times);
            }
        }
    }

    /**
     * 当freespin结束时的其他逻辑
     * @important 子类重载此方法时，必须调用parent::onFreespinOver
     */
    public function onFreespinOver()
    {
        $this->clearFreespin();

        $featureId = $this->getCurrFeature();

        $this->onFeatureEnd($featureId);

        //恢复进入freespin前的下注额
        if ($this->gameInfo['resumeBet']) {
            $this->setTotalBet($this->gameInfo['resumeBet']);
        }
    }

    /**
     * 判断feature是否自动激活
     */
    public function isAutoActivated($featureId)
    {
        return true;
    }

    /**
     * 生成feature编号
     */
    public function genFeatureNo()
    {
        return Utils::getRandChars(16);
    }

    /**
     * 设置用户当前所处feature
     * $featureId参数也可以传入featureName
     */
    public function setFeature($featureId, $featureDetail = array(), $totalBet = null, $featureNo = null)
    {
        if ($featureId && !isset($this->featureGames[$featureId])) {
            $featureId = $this->getFeatureByName($featureId);
            if (!$featureId) {
                FF::throwException(Code::SYSTEM_ERROR);
            }
        }

        $featureNo = $featureNo ?: $this->genFeatureNo();
        $activated = $this->isVirtualMode ? true : $this->isAutoActivated($featureId);
        $bakFeatures = $this->gameInfo['bakFeatures'];

        //feature中触发feature
        //将当前正在进行的feature信息存入备份的feature列表
        //备份的feature后入先出
        if ($currFeature = $this->gameInfo['featureId']) {
            if ($this->isFreeGame($currFeature) && $this->isFreeGame($featureId)) {
                $this->setFeatureDetail($featureDetail, false);
                return;
            }
            $bakFeatures[] = array(
                'featureId' => $currFeature,
                'featureNo' => $this->gameInfo['featureNo'],
                'featureDetail' => $this->gameInfo['featureDetail'],
            );
        }

        $this->updateGameInfo(array(
            'featureId' => $featureId,
            'featureNo' => $featureNo,
            'featureDetail' => $featureDetail,
            'bakFeatures' => $bakFeatures,
            'activated' => $activated ? 1 : 0,
        ));

        if (!$this->isSpinning) {
            $this->betContext['feature'] = $featureId;
            $this->betContext['featureNo'] = $featureNo;
        }

        if ($totalBet && $totalBet != $this->getTotalBet()) {
            $resumeBet = $this->getTotalBet();
            $this->setTotalBet($totalBet, $resumeBet);
        }

        Log::info('setFeature, featureId = ' . $featureId, 'slotsGame.log');
    }

    /**
     * 设置feature状态为已激活
     */
    public function setFeatureActivated()
    {
        $this->updateGameInfo(array('activated' => 1));
    }

    /**
     * 获取当前用户所处feature的详细信息
     */
    public function getFeatureDetail($key = null)
    {
        $featureDetail = $this->gameInfo['featureDetail'];

        return $key ? (isset($featureDetail[$key]) ? $featureDetail[$key] : null) : $featureDetail;
    }

    /**
     * 设置用户当前feature详细信息
     */
    public function setFeatureDetail($featureDetail = array(), $cover = true)
    {
        if (!$this->gameInfo['featureId']) return;

        if (!$cover) {
            $featureDetail = array_merge($this->gameInfo['featureDetail'], $featureDetail);
        }

        $this->updateGameInfo(array('featureDetail' => $featureDetail));
    }

    /**
     * 设置feature临时数据
     */
    public function setFeatureData($featureId, $data)
    {
        $this->featureData[$featureId] = $data;
    }

    /**
     * 恢复FreeGame时，获取需要传递给用户的feature详情
     */
    public function getFreeGameDetailOnResume()
    {
        return array();
    }

    /**
     * 清除用户当前feature信息
     */
    public function clearFeature($byRunOptions = false)
    {
        Log::info('clearFeature, featureId = ' . $this->getCurrFeature(), 'slotsGame.log');

        Feature::clearInstancesByMachine($this->getInstanceId());

        if (!$this->isSpinning) {
            $this->betContext['feature'] = '';
            $this->betContext['featureNo'] = '';
            $this->betContext['preFeatures'] = [];
            $this->betContext['totalWin'] = 0;
        }

        $this->updateGameInfo(array(
            'featureId' => '',
            'featureNo' => '',
            'featureDetail' => array(),
            'bakFeatures' => array(),
            'activated' => 0,
            'totalWin' => 0
        ));
    }

    /**
     * 判断触发的feature是否会中断spin
     */
    public function willBreakSpin($features)
    {
        if (!$features) return false;

        foreach ($features as $featureId) {
            if ($this->featureGames[$featureId]['breakSpin']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断Feature是否是选择模式
     */
    public function isChooseMode($featureId)
    {
        return $this->featureGames[$featureId]['chooseMode'] == 1;
    }

    /**
     * 当用户选择Feature模式时的逻辑
     */
    public function onChooseFeature($choosed)
    {
        $featureId = $this->getCurrFeature();

        return Feature::chooser($this, $featureId)->onChoose($choosed);
    }

    /**
     * 初始化freespin信息
     */
    public function initFreespin($times)
    {
        if (!$this->isVirtualMode) {
            Bll::freespin()->init($this->uid, $this->machineId, $times);
        }
        $this->freespinInfo = Bll::freespin()->getInitInfo($times);
    }

    /**
     * 获取freespin初始次数
     */
    public function getFreespinInitTimes($featureId)
    {
        return $this->getFeatureAwardFreeSpin($featureId, $this->featureGames[$featureId]);
    }

    /**
     * 增加freespin次数
     */
    public function addFreespinTimes($times)
    {
        if (!$this->isVirtualMode) {
            Bll::freespin()->addTimes($this->uid, $this->machineId, $times);
        }

        $this->freespinInfo['totalTimes'] += $times;
    }

    /**
     * 增加freespin已spin次数
     */
    public function incFreespinTimes()
    {
        if (!$this->isVirtualMode) {
            $currTimes = $this->freespinInfo['spinTimes'];
            $result = Bll::freespin()->incSpinTimes($this->uid, $this->machineId, $currTimes);
        } else {
            $result = true;
        }

        if ($result) {
            $this->freespinInfo['spinTimes'] += 1;
        }

        return $result;
    }

    /**
     * 判断当前是否是最后一次freespin
     */
    public function isLastFreeSpin()
    {
        if (isset($this->betContext['isLastFreeSpin'])) {
            return $this->betContext['isLastFreeSpin'];
        } else {
            $spinTimes = $this->getFreespinInfo('spinTimes');
            $totalTimes = $this->getFreespinInfo('totalTimes');
            return $totalTimes && ($spinTimes == $totalTimes);
        }
    }

    /**
     * 清除freespin信息
     */
    public function clearFreespin()
    {
        if (!$this->isVirtualMode) {
            Bll::freespin()->clearFreespin($this->uid, $this->machineId);
        }
        $this->freespinInfo = Bll::freespin()->getInitInfo();
    }

    /**
     * 检测feature异常
     */
    public function checkFeatureError()
    {
        $currFeature = $this->getCurrFeature();
        $freespinInfo = $this->getFreespinInfo();

        //freespin异常
        if ($freespinInfo['totalTimes'] && $freespinInfo['spinTimes'] >= $freespinInfo['totalTimes']) {
            if (!$currFeature || $this->isFreeGame($currFeature)) {
                $this->clearFreespin();
                if ($currFeature) {
                    $this->clearFeature();
                }
            }
        }
    }

    /**
     * 移动stick元素(默认不移动)
     */
    protected function moveStickyElements()
    {
        $stickyElements = $this->getFeatureDetail('stickyElements');

        if ($stickyElements) {
            $this->betContext['stickyElements'] = $stickyElements;
        }

        return $stickyElements ?: [];
    }

    /**
     * feature中掉落特殊元素
     */
    protected function dropElementsInFeature($featureId, $allElements, $disabledPoses = array(), $decreaseNum = 0)
    {
        if (!empty($this->featureData[$featureId]['elements'])) {
            return $this->featureData[$featureId]['elements'];
        }

        $dropElement = $this->featureGames[$featureId]['itemAward'];
        $limits = $this->featureGames[$featureId]['itemAwardLimit'];

        if (!$dropElement || !$limits) return array();
        if (empty($limits['reelRatios'])) return array();

        if (is_array($dropElement)) { //支持从多种元素中随机掉落一种
            $dropElement = $this->getDropElementIdInFeature($dropElement);
        }

        while (1) {
            if ($limits['limitType'] == 'totalNum') {
                $elements = $this->dropElementsByTotalNum($featureId, $dropElement, $allElements, $disabledPoses, $decreaseNum);
            } else {
                $elements = $this->dropElementsByReel($featureId, $dropElement, $allElements, $disabledPoses);
            }
            if ($elements !== false) {
                break;
            }
        }

        return $elements;
    }

    /**
     * 按列约束掉落元素
     */
    protected function dropElementsByReel($featureId, $elementId, $allElements, $disabledPoses = array())
    {
        $limits = $this->featureGames[$featureId]['itemAwardLimit'];

        if (empty($limits['numReelRatios'])) return [];

        //奖励元素出现的转轴个数只随机一次
        if (!isset($this->featureData[$featureId]['reelCount'])) {
            $reelCount = (int)Utils::randByRates($limits['numReelRatios']);
            $this->featureData[$featureId]['reelCount'] = $reelCount;
        } else {
            $reelCount = $this->featureData[$featureId]['reelCount'];
        }

        if (!$reelCount) return [];

        $newElements = array();
        $totalItemCount = 0;
        $reelRatios = array_filter($limits['reelRatios']);

        while ($reelCount) {
            if (!$reelRatios) break;
            $col = (int)Utils::randByRates($reelRatios);
            $itemCount = $this->dropElementsInCol(
                $featureId, $elementId, $col, $allElements, $newElements, $disabledPoses, $totalItemCount
            );
            $totalItemCount += $itemCount;
            unset($reelRatios[$col]);
            $reelCount--;
        }

        if (isset($limits['minTotalNum'])) {
            if ($totalItemCount < $limits['minTotalNum']) {
                return false;
            }
        }

        return $newElements;
    }

    /**
     * 按总数量约束掉落元素
     */
    protected function dropElementsByTotalNum($featureId, $elementId, $allElements, $disabledPoses = array(), $decreaseNum = 0)
    {
        $limits = $this->featureGames[$featureId]['itemAwardLimit'];

        if (empty($limits['numRatios'])) return [];

        //奖励元素出现的转轴个数只随机一次
        if (!isset($this->featureData[$featureId]['itemCount'])) {
            $totalItemCount = (int)Utils::randByRates($limits['numRatios']);
            $this->featureData[$featureId]['itemCount'] = $totalItemCount;
        } else {
            $totalItemCount = $this->featureData[$featureId]['itemCount'];
        }
        $totalItemCount -= $decreaseNum;

        if (!$totalItemCount) return [];

        $newElements = array();
        $reelRatios = array_filter($limits['reelRatios']);

        while ($totalItemCount) {
            if (!$reelRatios) break;
            $col = (int)Utils::randByRates($reelRatios);
            $itemCount = $this->dropElementsInCol(
                $featureId, $elementId, $col, $allElements, $newElements, $disabledPoses, $totalItemCount
            );
            $totalItemCount -= $itemCount;
            if (!$itemCount) {
                unset($reelRatios[$col]);
            }
        }

        return $newElements;
    }

    /**
     * 在指定列掉落新元素
     */
    protected function dropElementsInCol($featureId, $elementId, $col, $allElements, &$newElements, $disabledPoses, $maxNum = null)
    {
        $limits = $this->featureGames[$featureId]['itemAwardLimit'];
        $reelItemNums = $limits['reelItemNums'] ?? array();

        if (isset($reelItemNums[$col]) && !$reelItemNums[$col]) { //配置了该列不能掉落
            return 0;
        }

        $newColElements = $newElements[$col] ?: array();
        $disabledRows = $disabledPoses[$col] ?: array();

        $rows = $this->getDropAbleRows($featureId, $allElements[$col], $newColElements, $disabledRows);

        if (!$rows) { //该列无可用位置
            return 0;
        }

        //随机出掉落元素数量
        if (!empty($reelItemNums[$col])) {
            if (is_array($reelItemNums[$col])) {
                $itemCount = (int)Utils::randByRates($reelItemNums[$col]);
            } else {
                $itemCount = $reelItemNums[$col];
            }
        } else {
            // $itemCount = mt_rand(1, count($rows));
            $itemCount = 1;
        }

        //校正掉落元素数量
        $itemCount = min($itemCount, count($rows));
        if ($maxNum) {
            $itemCount = min($itemCount, $maxNum);
        }

        shuffle($rows);
        for ($i = 1; $i <= $itemCount; $i++) {
            $row = array_pop($rows);
            $newElements[$col][$row] = $elementId;
        }

        return $itemCount;
    }

    /**
     * 获取可掉落元素的行
     */
    protected function getDropAbleRows($featureId, $colElements, $newElements, $disabledRows)
    {
        $rows = array();
        $triggerOptions = $this->getTriggerOptions($featureId);
        $replaceWild = $triggerOptions['replaceWild'] ?? true;
        $replaceScatter = $triggerOptions['replaceScatter'] ?? true;
        $replaceBonus = $triggerOptions['replaceBonus'] ?? true;

        foreach ($colElements as $row => $elementId) {
            if (isset($newElements[$row])) continue;
            if (isset($disabledRows[$row])) continue;
            //部分feature掉落元素不可覆盖转出的特殊元素
            if ($this->isWildElement($elementId) && !$replaceWild) continue;
            if ($this->isScatterElement($elementId) && !$replaceScatter) continue;
            if ($this->isBonusElement($elementId) && !$replaceBonus) continue;
            $rows[] = (int)$row;
        }

        return $rows;
    }

    /**
     * 判断feature是否是FreeGame
     */
    public function isFreeGame($featureId)
    {
        if (!$featureId) return false;

        //先识别为featureName
        if (!isset($this->featureGames[$featureId])) {
            $featureId = $this->getFeatureByName($featureId);
            if (!$featureId) return false;
        }

        $freespinAward = $this->featureGames[$featureId]['freespinAward'];
        $featureName = $this->featureGames[$featureId]['featureName'];

        if ($freespinAward || in_array($featureName, [FEATURE_FREE_SPIN])) {
            return true;
        } elseif (strpos($featureName, FEATURE_FREE_SPIN) !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function hasFreeGame($features)
    {
        foreach ($features as $featureId) {
            if ($this->isFreeGame($featureId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断feature是否是Lightning
     */
    public function isLightning($featureId)
    {
        if (!$featureId) return false;

        //先识别为featureName
        if (strpos($featureId, FEATURE_LIGHTNING) !== false) {
            return true;
        }

        $featureName = $this->getFeatureName($featureId);

        return strpos($featureName, FEATURE_LIGHTNING) !== false;
    }

    /**
     * 判断当前是否在FreeGame中
     */
    public function isInFreeGame()
    {
        $currFeature = $this->gameInfo['featureId'];

        if (!$currFeature) {
            return false;
        } elseif ($this->isFreeGame($currFeature)) {
            return true;
        } elseif ($bakFeatures = $this->getBakFeatures()) {
            return $this->hasFreeGame($bakFeatures);
        } else {
            return false;
        }
    }

    /**
     * 判断是否触发了FreeGame
     */
    public function isFreeGameTriggered($features)
    {
        if (!$features) return false;

        foreach ($features as $featureId) {
            if ($this->isFreeGame($featureId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return BaseFeature|null
     */
    public function getFeaturePlugin($featureId)
    {
        return null;
    }

    /**
     * 是否根据掉落元素重算结果
     */
    public function isRecalculateByDropElements()
    {
        return true;
    }

    /**
     * 是否根据 Wild 翻倍重写计算结果
     */
    public function isRecalculateByWildMulti()
    {
        return true;
    }

    /**
     * 变动概率调整
     */
    public function revisionTriggerRatio($feature, $ratio)
    {
        return Utils::isHitByRate($ratio);
    }

    /**
     * 初始 Bingo 机台的 area
     */
    public function initMachineBingoArea()
    {
        return [];
    }

    /**
     * 获取掉落的元素ID
     */
    public function getDropElementIdInFeature($dropElement)
    {
        return Utils::randByRates($dropElement);
    }

    /**
     * 获取 feature 的 freeSpin 奖励
     */
    public function getFeatureAwardFreeSpin($featureId, $featureCfg, $itemCount = null)
    {
        if (is_null($itemCount)) {
            $itemCount = $this->featureData[$featureId]['triggerItemCount'];
        }

        if ($this->getCurrFeature() != $featureId) {
            $freeSpin = $featureCfg['freespinAward'];
        } else {
            $freeSpin = $featureCfg['extraTimes'];
        }

        $triggerItemNum = $featureCfg['triggerItemNum'];
        if (strripos($freeSpin, '|')) {
            $freeSpin = explode('|', $freeSpin);
            $triggerItemNum = explode('|', $triggerItemNum);
        }

        if (is_array($freeSpin)) {
            $index = array_search($itemCount, $triggerItemNum) !== false ?: count($freeSpin) - 1;
            $freeSpin = $freeSpin[$index] ?? array_last($freeSpin);
        }

        return $freeSpin;
    }

    public function addFeatureStep($step, $prizes, $elements = array(), $results = array(), $wheelId = null)
    {
        $featureStep = array(
            'step' => $step,
            'prizes' => $prizes,
            'elements' => $elements,
            'results' => $results,
            'wheelId' => $wheelId ?: '',
        );

        return $featureStep;
    }

    /**
     * Feature结束时，记录Feature步骤日志
     */
    public function onFeatureStepsCompleted($featureId, $featureSteps, $prizes)
    {
        // 更新 betId
        if (defined('TEST_ID')) {
            $betId = $this->betId . ':' . Utils::getRandChars(4);
        } else {
            $betId = $this->renewBetId();
        }

        // 下注次序
        $betSeq = $this->getAnalysisInfo('totalSpinTimes');
        $spinTimes = $this->gameInfo['spinTimes'];

        // 重写 betContext
        $betContext = $this->getBetContext();
        $betContext['isFreeSpin'] = true;
        $betContext['feature'] = $featureId;

        //下注日志
        Bll::log()->addBetLog(
            $betId, $this->uid, $this->machineId, $this->userInfo['level'],
            $betSeq, $spinTimes, $betContext, [], [], $prizes, false, $this->balance,
            Bll::session()->get('version'), $featureSteps
        );
    }

}