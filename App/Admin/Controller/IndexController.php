<?php
/**
 * 后台首页
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class IndexController extends BaseController
{
    /**
     * @ignore permission
     */
    public function index()
    {
        $data = array();
        $data['session'] = $this->session;
        $data['menus'] = Config::get('menus');
        $data['perms'] = Bll::perm()->getPermsInGroup($this->session['id'], 'menu1');
        $data['perms'] = array_merge($data['perms'], Bll::perm()->getPermsInGroup($this->session['id'], 'menu2'));
        $data['appName'] = Config::get('app-store', 'name');
        $data['appHosts'] = Config::get('app-host');

        $this->display('index.html', $data);
    }

    /**
     * @ignore permission
     */
    public function welcome()
    {
        $this->display('welcome.html');
    }

    /**
     * @ignore permission
     */
    public function merge()
    {
        $testId = $this->getParam('testId', false);
        $action = $this->getParam('action', false, 'reset');
        $isReset = $action === 'reset';

        if ($isReset || !$testId) $testId = Utils::getRandChars(8);

        $key = Keys::buildKey('CandyMergeTest',$testId);

        $steps = !$isReset ? Dao::redis()->get($key) : [];
        $steps = $steps ? json_decode($steps, true) : [];

        $elements = $steps ? array_last($steps) : [];

        $key1 = Keys::buildKey('CandyMergedTestTimes', $testId);
        $times = Dao::redis()->incr($key1);

        if ($times <= 8) {
            for ($j = 1; $j <= 5; $j++) {
                for ($i = 1; $i <= 4; $i++) {
                    if (isset($elements[$j][$i]) || Utils::isHitByRate(0.5)) {
                        continue;
                    }
                    $elements[$j][$i] = 1;
                }
            }

            $steps[] = $elements;

            $mergeRule = array(1 => 2, 2 => 3, 3 => 4, 4 => 5);

            foreach ($mergeRule as $searchElm => $advancedElm) {
                $elementX4List = $this->searchElementPX4($elements, $searchElm);
                $this->elementPX4Merge($elements, $elementX4List, $advancedElm, $steps);
                $elementPX2List = $this->searchElementPX2($elements, $searchElm, $advancedElm);

                $this->elementXP2Merge($elements, $elementPX2List, $advancedElm, $steps);
            }
        }

        Dao::redis()->set($key, json_encode($steps), 3600);
        Dao::redis()->expire($key1, 3600);

        $this->display('merge.html', ['steps' => $steps, 'testId' => $testId, 'times' => max(0, 8 - $times)]);
    }

    public function elementPX4Merge(&$elements, $elementX4List, $advancedElm, &$steps)
    {
        if (!$elementX4List) return;

        foreach ($elementX4List as $elementX4s) {

            $fElm = $elementX4s[0];
            $sElm = $elementX4s[1];
            $thElm = $elementX4s[2];
            $ftElm = $elementX4s[3];

            unset($elements[$fElm[0]][$fElm[1]]);
            $elements[$sElm[0]][$sElm[1]] = $advancedElm;
            $steps[] = $elements;

            unset($elements[$ftElm[0]][$ftElm[1]]);
            $elements[$thElm[0]][$thElm[1]] = $advancedElm;
            $steps[] = $elements;
        }
    }

    public function elementXP2Merge(&$elements, $elementPX2List, $advancedElm, &$steps)
    {
        if (!$elementPX2List) return;

        foreach ($elementPX2List as $elementX2s) {

            $fElm = $elementX2s[0];
            $sElm = $elementX2s[1];

            unset($elements[$fElm[0]][$fElm[1]]);
            $elements[$sElm[0]][$sElm[1]] = $advancedElm;
            $steps[] = $elements;
        }
    }

    //判断4个有多少组
    protected function searchElementPX4($elements, $searchElmId = 1)
    {
        $elementList = $this->elementsToList($elements);
        $valuesCnt = array_count_values(array_column($elementList, 'elementId'));

        if (empty($valuesCnt[$searchElmId]) || $valuesCnt[$searchElmId] < 4) return [];

        $patternList = Config::get('common/candy-X4-patterns');
        $elmX4List = [];
        $searchedCnt = 0;

        while ($elementInfo = array_shift($elementList)) {
            if ($elementInfo['elementId'] != $searchElmId) continue;

            $searchedCnt++;
            foreach ($patternList as $patterns) {
                $matchedList = [];
                $tmpElements = $elements;
                $optimalElms = array();
                $matched = true;
                foreach ($patterns as $poses) {
                    $row = $poses[1] + $elementInfo['row'];
                    $col = $poses[0] + $elementInfo['col'];

                    if (!isset($elements[$col][$row]) || $elements[$col][$row] != $searchElmId) {
                        $matched = false;
                        break;
                    }
                    unset($tmpElements[$col][$row]);
                    $optimalElms[] = [$col, $row];
                }

                if (!$matched) continue;

                $matchedList[] = $optimalElms;
                if ($valuesCnt[$searchElmId] > 7) {
                    $result = $this->searchElementPX4($tmpElements, $searchElmId);
                    $matchedList = array_merge($matchedList, $result);
                }

                if (count($elmX4List) < $matchedList) {
                    $elmX4List = $matchedList;
                }

                $subCount = $valuesCnt[$searchElmId] - count($elmX4List) * 4;

                if ($subCount < 4) {
                    break 2;
                }
            }

            if ($valuesCnt[$searchElmId] <= $searchedCnt) break;

        }

        return $elmX4List;
    }

    //判断2个有多少组
    protected function searchElementPX2($elements, $searchElm, $advancedElm)
    {
        $elmPX2List = $this->searchNearPX2ByAdvancedElement($elements, $searchElm, $advancedElm);
        $patterns = array([-1, 0], [1, 0], [0, -1], [0, 1]);

        $elementList = $this->elementsToList($elements);
        foreach ($elementList as $element) {
            if ($element['elementId'] != $searchElm || !isset($elements[$element['col']][$element['row']])) continue;

            foreach ($patterns as $pattern) {
                $col = $pattern[0] + $element['col'];
                $row = $pattern[1] + $element['row'];

                if (!isset($elements[$col][$row]) || $elements[$col][$row] != $searchElm) {
                    continue;
                }

                unset($elements[$col][$row], $elements[$element['col']][$element['row']]);
                $elmPX2List[] = [[$element['col'], $element['row']], [$col, $row]];

                break;
            }
        }

        return $elmPX2List;
    }

    public function searchNearPX2ByAdvancedElement(&$elements, $searchElm, $advancedElm)
    {
        $elmPX2List = [];
        $patterns = array([-1, 0], [1, 0], [0, -1], [0, 1]);
        //先查找高级元素附近是否有对应的低级元素
        $elementList = $this->elementsToList($elements);
        foreach ($elementList as $element) {
            if ($element['elementId'] != $advancedElm) continue;

            foreach ($patterns as $pattern) {
                $col = $pattern[0] + $element['col'];
                $row = $pattern[1] + $element['row'];

                if (!isset($elements[$col][$row]) || $elements[$col][$row] != $searchElm) {
                    continue;
                }
                $oPatterns = $patterns;
                foreach ($oPatterns as $oPattern) {
                    $col1 = $oPattern[0] + $col;
                    $row1 = $oPattern[1] + $row;
                    if (!isset($elements[$col1][$row1]) || $elements[$col1][$row1] != $searchElm) {
                        continue;
                    }

                    $elmPX2List[] = [[$col1, $row1], [$col, $row]];
                    unset($elements[$col][$row], $elements[$col1][$row1]);
                    break;
                }
            }
        }

        return $elmPX2List;
    }

    public function elementsToList($elements)
    {
        if (!$elements) return array();

        //已经是List结构
        if (isset($elements[0])) return $elements;

        $list = array();
        ksort($elements, SORT_NUMERIC);
        foreach ($elements as $col => $_elements) {
            ksort($_elements, SORT_NUMERIC);
            foreach ($_elements as $row => $elementId) {
                $list[] = array(
                    'elementId' => $elementId,
                    'col' => (int)$col,
                    'row' => (int)$row
                );
            }
        }

        return $list;
    }
}