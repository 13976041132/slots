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
        if ((defined('IV_ENABLE') && !IV_ENABLE) || defined('SAMPLE_PLAN_ID')) {
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

        $this->analysisInfo['lastRechargeFeatureNum'] = $this->analysisInfo['totalSpinTimes'] + 1;
    }

    public function checkBankruptcyInterveneExit()
    {
        if (empty($this->betContext['interveneType']) || $this->betContext['interveneType'] !== 'Bankrupt') return;

        $interveneInfo = $this->analysisInfo['bankruptcyInfo'] ?? [];

        if (isset($interveneInfo['iHitBankruptcyBet'])) {
            $interveneInfo['betIndex'] = $this->getTotalBetIndex($interveneInfo['iHitBankruptcyBet']);
        }

        $userInfo = array(
            'machineId' => $this->machineId,
            'balance' => $this->balance,
            'betIndex' => $this->getTotalBetIndex(),
        );

        if (Bll::ivBankruptEvent()->checkInterveneExit($this->uid, $interveneInfo, $userInfo)) {
            Log::info(['Bankrupt', 'Exit', $this->uid, $interveneInfo, $userInfo], 'intervene.log');

            $this->analysisInfo['bankruptcyInfo']['lastHitType'] = $this->analysisInfo['bankruptcyInfo']['hitType'];
            $this->analysisInfo['bankruptcyInfo']['hitType'] = 0;
            $this->analysisInfo['bankruptcyInfo']['iLastHitCD'] = $this->analysisInfo['bankruptcyInfo']['iCurHitCD'];
            $this->analysisInfo['bankruptcyInfo']['iHitBankruptcyRate'] = 0;
            $this->analysisInfo['bankruptcyInfo']['iHitInterveneTimes'] = 0;
        }
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
            'spinTimes' => $this->analysisInfo['totalSpinTimes'] + 1,
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
            'lastRechargeFeatureNum' => $this->analysisInfo['lastRechargeFeatureNum'] ?? 0
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

        $spinTimes = $this->analysisInfo['totalSpinTimes'] ?? 0;
        $checkData = array(
            'machineId' => $this->machineId,
            'spinTimes' => (int)$spinTimes,
            'notHitTimes' => $spinTimes - ($this->analysisInfo['lastWinNum'] ?? 0),
            'notFeatureTimes' => $spinTimes - ($this->analysisInfo['lastFeatureNum'] ?? 0),
            'notBigWinTimes' => $spinTimes - ($this->analysisInfo['lastBigWinNum'] ?? 0),
            'isRelativeBankrupt' => !empty($this->analysisInfo['firstBankruptcyNum']) ? 'Y' : 'N',
            'maxBetRatio' => $this->balance / max(array_values($this->betOptions)),
            'balance' => $this->balance
        );

        $result = Bll::ivExtremeEvent()->checkInterveneTrigger($this->uid, $checkData);

        $reback = $this->dealEventIntervene($result['interveneFlag'], $result);
        if ($result['isIntervene']) {
            Log::info(['checkExtremeEventIntervene', $this->uid, $result, $checkData, $reback], 'intervene.log');
        }
        return $reback;
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

        // 是否进行过破产干预
        $isBankrupt = !empty($this->analysisInfo['bankruptcyInfo']['lastHitType']);

        $checkData = array(
            'machineId' => $this->machineId,
            'totalBet' => $this->getTotalBet(),
            'betIndex' => $this->getTotalBetIndex(),
            'betRatio' => $this->betContext['betRatio'],
            'isBankrupt' => $isBankrupt,
            'isRecharge' => Bll::userAdapter()->getRechargeInfo($this->uid, 'times') > 0,
            'registerTime' => Bll::userAdapter()->getUserInfo($this->uid, 'createTime') ?: time(),
            'loginDays' => (int)$this->getUserInfo('loginDays'),
            'spinTimes' => $this->analysisInfo['totalSpinTimes'],
            'balance' => $this->balance,
            'winMultiples' => json_decode($this->machine['winMultiples'], true),
            'interveneInfo' => $this->analysisInfo['bankruptcyInfo'] ?? []
        );

        if (isset($checkData['interveneInfo']['iHitBankruptcyBet'])) {
            $checkData['interveneInfo']['betIndex'] = $this->getTotalBetIndex($checkData['interveneInfo']['iHitBankruptcyBet']);
        }

        $result = Bll::ivBankruptEvent()->checkInterveneTrigger($this->uid, $checkData);

        // 干预，需要更新 interveneInfo
        if ($result['interveneInfo']) {
            $this->analysisInfo['bankruptcyInfo'] = array_merge($this->analysisInfo['bankruptcyInfo'] ?? [], $result['interveneInfo']);
            unset($result['interveneInfo']);
        }

        return $this->dealEventIntervene('Bankrupt', $result);
    }

    /**
     * spin结束后更新干预信息
     */
    public function updateInterveneInfo($totalWin)
    {
        // 破产干预更新数据
        if (!empty($this->betContext['interveneType']) && $this->betContext['interveneType'] == 'Bankrupt') {
            $this->analysisInfo['bankruptcyInfo']['iHitInterveneTimes']++;
            $multiple = ceil($totalWin / $this->getTotalBet());
            if ($multiple > 0) {
                $this->analysisInfo['bankruptcyInfo']['iHitBankruptcyRate'] += $multiple;
            }
        }
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
            'spinTimes' => $this->analysisInfo['totalSpinTimes'] + 1,
            'regCoins' => 2000000,
            'balance' => $this->balance,
        ));

        // 执行干预内容
        return $this->dealEventIntervene('Novice', $result);
    }

    /**
     * 获取 bigWin 干预过滤元素
     */
    public function getBigWinInterveneExcludeElements()
    {
        // 通过当前使用的轴样本进行过滤
        $sampleId = $this->betContext['sampleId'];
        $sampleItems = $this->sampleItems[$sampleId];
        $includeElements = [];
        foreach ($sampleItems as $col => $sampleItem) {
            $includeElements = array_unique(array_merge($includeElements, $sampleItem));
        }

        $excludeElements = array_diff(array_keys($this->machineItems), $includeElements);
        $excludeElements = array_merge(
            $excludeElements,
            $this->scatterElements,
            $this->bonusElements,
            $this->frameElements
        );

        return $excludeElements;
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
     * 生成中奖牌面
     * 1、去除预触发掉落的 feature
     * 2、跳过 wild/scatter/bonus
     */
    public function makeReelElementsWithWinMultiple($elements)
    {
        // 检查 预触发 feature 内容
        $this->checkFeatureInInWinMultipleIntervene();
        $randomElements = $this->interveneInfo['winMulti']['interveneElements'] ?? [];

        if ($randomElements && !in_array('*', $randomElements)) {
            return $this->makeReelElementsWithSame($randomElements);
        }

        return $this->makeReelElementsWithRandom($elements);
    }

    /**
     * 生成一样的牌面
     */
    public function makeReelElementsWithSame($elementIds)
    {
        $elements = [];
        shuffle($elementIds);
        $elementId = array_shift($elementIds);
        $cols = $this->machine['cols'];
        $rows = $this->machine['rows'];

        for ($i = 1; $i <= $cols; $i++) {
            for ($j = 1; $j <= $rows; $j++) {
                $elements[$i][$j] = $elementId;
            }
        }

        return $elements;
    }

    public function makeReelElementsWithRandom($elements)
    {
        // 目标倍数
        $winMulti = !empty($this->runOptions['winType']) ? $this->getWinMultiByWinType($this->runOptions['winType']) : $this->runOptions['winMulti'];

        // 过滤特殊元素
        $excludeElements = $this->getBigWinInterveneExcludeElements();

        // 排除位置 scatter/bonus
        $excludeMap = [];
        foreach ($elements as $col => $rowElements) {
            foreach ($rowElements as $row => $elementId) {
                if (!in_array($elementId, $excludeElements)) continue;
                $excludeMap[$col][] = $row;
            }
        }

        // 首列全不可用的情况下，无可用方案
        if (isset($excludeMap[1]) && count($excludeMap[1]) == $this->machine['rows']) {
            return $elements;
        }

        // 生成用 paytable元素长度 作为索引的 payline
        $colPayLine = [];
        if (!empty($this->paylines)) {
            // 非全线机台，可用中奖线
            for ($length = 1; $length <= $this->machine['cols']; $length++) {
                $tempPayLine = [];
                $colPayLine[$length] = [];
                foreach ($this->paylines as $resultId => $result) {
                    // 排除位置的中奖线
                    $isExclude = false;
                    for ($col = 1; $col <= $length; $col++) {
                        if (!empty($excludeMap[$col]) && in_array($result['route'][$col - 1], $excludeMap[$col])) {
                            $isExclude = true;
                            break;
                        }
                    }
                    if ($isExclude) continue;
                    $tempPayLine[$resultId] = $result;
                }
                $colPayLine[$length]['paylines'] = $tempPayLine;
                $colPayLine[$length]['paylinesNum'] = count($tempPayLine);
            }
        } else {
            // 全线机台，可用中奖线数量
            for ($length = 1; $length <= $this->machine['cols']; $length++) {
                $paylinesNum = 1;
                $enableRows = [];
                for ($col = 1; $col <= $length; $col++) {
                    $enableRow = $this->machine['rows'] - (empty($excludeMap[$col]) ? 0 : count($excludeMap[$col]));
                    $paylinesNum *= $enableRow;
                    $enableRows[] = $enableRow;
                }
                $colPayLine[$length]['enableRows'] = $enableRows;
                $colPayLine[$length]['paylinesNum'] = $paylinesNum;
            }
        }

        // 【优化方案】计算奖励组合 使用哪几个 paytable 和 payline 进行组合
        // 【简单方案】取最少中奖线 和 paytable 组合，且满足 winMulti 的结果
        $availableResults = [];
        $highAbleResults = [];
        foreach ($this->paytable as $resultId => $result) {
            $length = count(array_diff($result['elements'], [0]));
            if ($colPayLine[$length]['paylinesNum'] <= 0) continue;

            // 排除元素
            $hasExclude = array_intersect($result['elements'], $excludeElements);
            if ($hasExclude) continue;

            // 不满足条件
            $curLineNum = $winMulti / ($result['prize'] / 100);
            if ($curLineNum >= $colPayLine[$length]['paylinesNum']) continue;
            $result['payLineNumFloat'] = $curLineNum;
            $result['payLineNum'] = ceil($curLineNum);
            if ($curLineNum >= 1) {
                $availableResults[$resultId] = $result;
            } else {
                if (!empty($highAbleResults) && $highAbleResults['payLineNumFloat'] >= $curLineNum) continue;
                $highAbleResults = $result;
            }
        }

        // 没有满足条件的方案，考虑排除
        if (!$availableResults && !$highAbleResults) {
            return $elements;
        }

        // 没有满足条件时，使用高倍数结果
        if (!$availableResults) {
            $availableResults[$highAbleResults['resultId']] = $highAbleResults;
        }

        // 取随机结果
        $ableResult = $availableResults[array_rand($availableResults)];
        $length = count(array_diff($ableResult['elements'], [0]));

        // 区分全牌面和非全牌面
        if (isset($colPayLine[$length]['paylines'])) {
            $paylines = $colPayLine[$length]['paylines'];
            for ($index = 0; $index < $ableResult['payLineNum']; $index++) {
                $resultKey = array_rand($paylines);
                $payline = $paylines[$resultKey];
                for ($rIndex = 0; $rIndex < $length; $rIndex++) {
                    $elementId = $ableResult['elements'][$rIndex];
                    if (strstr($elementId, '*') !== false) {
                        $elementId = $this->getMatchRandomElementId($elementId);
                    }
                    $col = $rIndex + 1;
                    $row = $payline['route'][$rIndex];
                    $elements[$col][$row] = $elementId;

                    // 移除 stack 元素标识
                    if (isset($this->elementValues)) {
                        unset($this->elementValues[$col][$row]);
                    }
                }
                unset($paylines[$resultKey]);
            }
        } else {
            $enableRows = $colPayLine[$length]['enableRows'];

            // 极限倍数计算
            $takeRows = [];
            for ($index = 0; $index < $length; $index++) {
                $takeRows[$index] = $enableRows[$index];
                for ($rIndex = $length - 1; $rIndex > $index; $rIndex--) {
                    $takeRows[$index] *= $enableRows[$rIndex];
                }
            }

            // 计算每列替换个数
            $replaceRows = [];
            $willMulti = 1;
            for ($index = 0; $index < $length; $index++) {
                // 下一个值是否满足剩余倍数
                $min = min(ceil($ableResult['payLineNum'] / $willMulti / (isset($takeRows[$index + 1]) ? $takeRows[$index + 1] : 1)), $enableRows[$index]);
                $max = min(ceil($ableResult['payLineNum'] / $willMulti), $enableRows[$index]);
                $randomNum = rand($min, $max);
                $replaceRows[] = $randomNum;
                $willMulti *= $randomNum;
            }

            // 更新牌面结果
            for ($index = 0; $index < $length; $index++) {
                $elementId = $ableResult['elements'][$index];
                if (strstr($elementId, '*') !== false) {
                    $elementId = $this->getMatchRandomElementId($elementId);
                }
                $ableRows = array_diff(range(1, $this->machine['rows']), isset($excludeMap[$index + 1]) ? $excludeMap[$index + 1] : []);
                for ($rIndex = 0; $rIndex < $replaceRows[$index]; $rIndex++) {
                    $useRowKey = array_rand($ableRows);
                    $col = $rIndex + 1;
                    $row = $ableRows[$useRowKey];
                    $elements[$col][$row] = $elementId;
                    unset($ableRows[$useRowKey]);

                    // 移除 stack 元素标识
                    if (isset($this->elementValues)) {
                        unset($this->elementValues[$col][$row]);
                    }
                }
            }
        }

        return $elements;
    }

    /**
     * 获取匹配的随机元素
     */
    public function getMatchRandomElementId($pregElementId)
    {
        $allElements = [];
        $pregElement = str_replace('*', '.', $pregElementId);
        foreach ($this->machineItems as $elementId => $elementVal) {
            $matchNum = preg_match("/" . $pregElement . "/", $elementId);
            if ($matchNum < 1) continue;
            $allElements[] = $elementId;
        }

        return $allElements[array_rand($allElements)];
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