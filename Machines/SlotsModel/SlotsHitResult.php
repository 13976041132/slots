<?php
/**
 * Slots中奖结果逻辑
 */

namespace FF\Machines\SlotsModel;

abstract class SlotsHitResult extends SlotsConstructor
{
    protected $isClassic = false;

    /**
     * 校正中奖结果
     */
    protected function checkResultIds($resultIds)
    {
        if ($this->paylines || count($resultIds) <= 1) {
            return $resultIds;
        }

        //[全线机台]
        //同一元素过滤掉较小的中奖组合，只算最大奖励
        $resultGroups = array();
        foreach ($resultIds as $resultId) {
            $elements = array_filter($this->paytable[$resultId]['elements']);
            $elementId = (string)$elements[0];
            $length = count($elements);
            if (!isset($resultGroups[$elementId])) {
                $resultGroups[$elementId] = array();
            }
            $resultGroups[$elementId][$length] = $resultId;
        }

        $resultIds = array();
        foreach ($resultGroups as $resultGroup) {
            $maxLen = max(array_keys($resultGroup));
            $resultIds[] = $resultGroup[$maxLen];
        }

        return $resultIds;
    }

    /**
     * 根据已分配元素计算中奖结果
     * 兼容全线机台与非全线机台
     */
    public function getHitResultIds($elements, $values = null)
    {
        //粘连元素替换对应位置的轴元素
        $featureDetail = $this->gameInfo['featureDetail'];
        if (!empty($featureDetail['stickyElements'])) {
            $elements = $this->elementsMerge($elements, $featureDetail['stickyElements']);
        }

        if ($this->paylines) {
            $hitResultIds = array();
            foreach ($this->paylines as $lineId => $payline) {
                $resultId = $this->checkLinePrized($lineId, $elements);
                if ($resultId) {
                    $hitResultIds[$lineId] = $resultId;
                }
            }
        } else {
            $hitResultIds = $this->getFullLineHitResultIds($elements, $values);
        }

        $this->checkHitResultIds($hitResultIds);

        return $hitResultIds;
    }

    /**
     * 获取全线机台的中奖结果
     */
    protected function getFullLineHitResultIds($elements, $values)
    {
        //替换元素
        foreach ($elements as $col => $_elements) {
            foreach ($_elements as $row => $elementId) {
                if (isset($this->machineItems[$elementId]['options']['replaceTo'])) {
                    $elements[$col][$row] = $this->machineItems[$elementId]['options']['replaceTo'];
                }
            }
        }

        //统计每列每个元素出现的个数
        $elementStats = $this->getElementsStats($elements);

        //每个元素出现的最大长度
        $elementMaxLen = $this->getElementsLength($elementStats);

        //第一列出现wild时，需要计算后面几列的元素出现的最大长度
        $wildElementId = $this->wildElements[0] ?? '';
        if (isset($elementMaxLen[$wildElementId])) {
            $wildNum = $elementMaxLen[$wildElementId];
            for ($col = 2; $col <= $wildNum + 1; $col++) {
                $_elementMaxLen = $this->getElementsLength($elementStats, $col);
                foreach ($_elementMaxLen as $elementId => $length) {
                    if ($elementId == $wildElementId) continue;
                    $len = $col - 1 + $length;
                    if (isset($elementMaxLen[$elementId])) {
                        $elementMaxLen[$elementId] = max($elementMaxLen[$elementId], $len);
                    } else {
                        $elementMaxLen[$elementId] = $len;
                    }
                }
            }
        }

        $hitResultIds = array();
        $paytableGroup = $this->getPaytableGroup($this->isInFreeGame());

        foreach ($elementMaxLen as $elementId => $len) {
            if (!isset($paytableGroup[$elementId])) continue;
            if (!isset($paytableGroup[$elementId][$len])) continue;
            $resultId = $paytableGroup[$elementId][$len];
            $resultCount = 1;
            for ($col = 1; $col <= $len; $col++) {
                $elementCount = 0;
                if (isset($elementStats[$col][$elementId])) {
                    $elementCount += $elementStats[$col][$elementId];
                }
                if ($elementId != $wildElementId && isset($elementStats[$col][$wildElementId])) {
                    $elementCount += $elementStats[$col][$wildElementId];
                    if ($values && !empty($values[$col])) { //Wild翻倍
                        foreach ($values[$col] as $row => $value) {
                            $elementCount += (int)$value - 1;
                        }
                    }
                }
                $resultCount *= $elementCount;
            }
            $hitResultIds["{$resultId}:{$resultCount}"] = $resultId;
        }

        return $hitResultIds;
    }

    /**
     * 每列上的元素个数统计
     * 如果Wild元素有多种，则都算到第一种Wild上
     */
    protected function getElementsStats($elements)
    {
        $elementStats = array();
        $wildElementId = $this->wildElements[0] ?? '';

        for ($col = 1; $col <= $this->machine['cols']; $col++) {
            $counts = array_count_values($elements[$col]);
            foreach ($this->wildElements as $_wildElementId) {
                if ($_wildElementId != $wildElementId && isset($counts[$_wildElementId])) {
                    $counts[$wildElementId] += $counts[$_wildElementId];
                    unset($counts[$_wildElementId]);
                }
            }
            $elementStats[$col] = $counts;
        }

        return $elementStats;
    }

    /**
     * 检查指定line是否中奖，若中奖则返回对应resultId
     */
    protected function checkLinePrized($lineId, $allElements)
    {
        $elements = $this->getElementsOnline($lineId, $allElements);

        $resultId = $this->getMatchedResult($elements);

        return $resultId ?  : false;
    }

    /**
     * 校正中奖线、中奖组合
     */
    protected function checkHitResultIds(&$hitResultIds)
    {
        //to override
    }

    /**
     * 计算每个元素出现的最大长度(从指定列开始)
     */
    public function getElementsLength($elementStats, $startCol = 1)
    {
        $elementMaxLen = array();
        if ($startCol > $this->machine['cols']) return array();
        $wildElement = $this->wildElements[0] ?? '';

        foreach ($elementStats[$startCol] as $elementId => $count) {
            $elementMaxLen[$elementId] = 1;
            for ($col = $startCol + 1; $col <= $this->machine['cols']; $col++) {
                if (isset($elementStats[$col][$elementId])) {
                    $elementMaxLen[$elementId]++;
                } elseif ($wildElement && isset($elementStats[$col][$wildElement])) {
                    $elementMaxLen[$elementId]++;
                } else {
                    break;
                }
            }
        }

        return $elementMaxLen;
    }

    /**
     * 获取中奖线上的中奖元素
     * [非全线机台]
     */
    public function getElementsOnline($lineId, $allElements, $toPoints = false)
    {
        $elements = array();
        $routes = $this->paylines[$lineId]['route'];
        $cols = count($routes);

        for ($col = 1; $col <= $cols; $col++) {
            $row = $routes[$col - 1];
            if (!empty($allElements[$col][$row])) {
                $elementId = $allElements[$col][$row];
            } else {
                $elementId = '0';
            }
            if ($toPoints) {
                $elements[$col][$row] = $elementId;
            } else {
                $elements[] = $elementId;
            }
        }

        return $elements;
    }

    /**
     * 获得指定元素组合的中奖结果
     * [非全线机台]
     */
    public function getMatchedResult($elements)
    {
        if ($this->isInFreeGame() && $this->paytableFreeSpin) {
            $paytable = $this->paytableFreeSpin;
        } else {
            $paytable = $this->paytableGeneral;
        }

        //先扫描出完全匹配的中奖组合
        if (!in_array('0', $elements, true)) {
            if ($this->isClassic) sort($elements);
            $elementsStr = implode(',', $elements);
            foreach ($paytable as $resultId => $v) {
                $_elements = $v['elements'];
                if (in_array('0', $_elements, true)) continue;
                if ($this->isClassic) sort($_elements);

                // 修改为通配符（正则）匹配
                $pregElements = str_replace('*', '.', implode(',', $_elements));
                $matcheNum = preg_match("/".$pregElements."/", $elementsStr);
                if ($matcheNum > 0) {
                    return $resultId;
                }
            }
        }

        //payline上是否有wild
        $hasWild = false;
        foreach ($elements as $elementId) {
            if ($elementId && $this->isWildElement($elementId)) {
                $hasWild = true;
                break;
            }
        }

        //扫描出模糊匹配的中奖组合(可能有多个)
        $resultIds = array();
        foreach ($paytable as $resultId => $v) {
            if (!$this->isClassic && !$hasWild && !in_array('0', $v['elements'], true)) {
                continue;
            }
            if ($this->checkMatched($elements, $resultId)) {
                $resultIds[] = $resultId;
            }
        }

        if (!$resultIds) return '';

        //选出奖励最高的结果
        if (count($resultIds) > 1) {
            $resultId = $this->getMaxPrizedResult($elements, $resultIds);
        } else {
            $resultId = $resultIds[0];
        }

        return $resultId;
    }

    /**
     * 检查某元素组合是否与指定中奖组合匹配
     * [非全线机台]
     */
    protected function checkMatched($elements, $resultId)
    {
        $matched = true;
        $payElements = $this->paytable[$resultId]['elements'];

        //Classic机台中奖无须从最左列连续命中
        //中奖组合可能是不同元素的组合，且同一payline可能中多个奖
        //有多种wild时，不同wild不能互相替换
        if ($this->isClassic) {
            $wildElementId = isset($this->wildElements[0]) ? $this->wildElements[0] : '';
            $wildElement2Id = isset($this->wildElements[1]) ? $this->wildElements[1] : '';
            $elementsCount = array_count_values($elements);
            foreach ($payElements as $payElementId) {
                if (!$payElementId) break;
                if (!empty($elementsCount[$payElementId])) {
                    $elementsCount[$payElementId]--;
                } elseif (!$this->isWildElement($payElementId)) {
                    // 通配符匹配模式
                    if (strpos($payElementId, '*') !== false) {
                        $pregElement = str_replace('*', '.', $payElementId);
                        $isMatch = false;
                        foreach ($elementsCount as $elementId => $elementNum) {
                            if ($elementNum > 0 && preg_match("/".$pregElement."/", $elementId) > 0) {
                                $isMatch = true;
                                $elementsCount[$elementId]--;
                                break;
                            }
                        }
                        if ($isMatch) {
                            continue;
                        }
                    }
                    if ($wildElementId && !empty($elementsCount[$wildElementId])) {
                        $elementsCount[$wildElementId]--;
                    } elseif ($wildElement2Id && !empty($elementsCount[$wildElement2Id])) {
                        $elementsCount[$wildElement2Id]--;
                    } else {
                        $matched = false;
                        break;
                    }
                } else {
                    $matched = false;
                    break;
                }
            }
            return $matched;
        }

        //非Classic机台中奖必须从最左列连续命中，且只能命中指定个数的相同元素
        foreach ($payElements as $k => $payElementId) {
            $elementId = $elements[$k];
            if ($payElementId) {
                // 通配符匹配模式
                if (strpos($payElementId, '*') !== false) {
                    $pregElement = str_replace('*', '.', $payElementId);
                    if (preg_match("/".$pregElement."/", $elementId) == 0 && !$this->isWildElement($elementId)) {
                        $matched = false;
                        break;
                    }
                } else if ($elementId != $payElementId && !$this->isWildElement($elementId)) {
                    $matched = false;
                    break;
                }
            } else {
                if ($elementId == $payElements[0] || $this->isWildElement($elementId)) {
                    $matched = false;
                }
                break;
            }
        }

        return $matched;
    }

    /**
     * 同一line中多个奖时，选出奖励最高的结果
     * [非全线机台]
     */
    public function getMaxPrizedResult($elements, $resultIds)
    {
        $resultId = array_shift($resultIds);

        foreach ($resultIds as $_resultId) {
            if ($this->paytable[$_resultId]['prize'] > $this->paytable[$resultId]['prize']) {
                $resultId = $_resultId;
            }
        }

        return $resultId;
    }

    /**
     * 根据中奖结果检出参与中奖的元素
     */
    public function getHitElements($elements, $hitResult)
    {
        $hitElements = array();

        foreach ($hitResult as $result) {
            $routes = $result['lineRoute'];
            foreach ($result['elements'] as $k => $payElement) {
                if (!$payElement) continue;
                $col = $k + 1;
                if (!$this->paylines) {
                    foreach ($elements[$col] as $row => $elementId) {
                        if ($elementId == $payElement || $this->isWildElement($elementId)) {
                            $hitElements[$col][$row][$payElement] = 1;
                        }
                    }
                } else {
                    $row = $routes[$k];
                    $hitElements[$col][$row][$payElement] = 1;
                }
            }
        }

        foreach ($hitElements as $col => $colElements) {
            foreach ($colElements as $row => $rowElements) {
                $hitElements[$col][$row] = array_keys($rowElements);
            }
        }

        return $hitElements;
    }

    /**
     * 通过行号取某一行的元素列表
     */
    public function getElementsByRow($row, $allElements)
    {
        $elements = array();
        foreach ($allElements as $col => $rowElements) {
            $elements[$col][$row] = $rowElements[$row];
        }
        return $elements;
    }
}