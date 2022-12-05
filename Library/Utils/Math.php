<?php
/**
 * 数学函数
 */

namespace FF\Library\Utils;

class Math
{
    /**
     * 从多个集合中各取一个元素得到一个组合，返回所有组合
     * @param $itemGroups
     * @return array
     */
    public static function group($itemGroups)
    {
        $groups = array();

        if (count($itemGroups) == 1) {
            foreach ($itemGroups[0] as $item) {
                $groups[] = [$item];
            }
            return $groups;
        }

        $items = array_shift($itemGroups);
        $_groups = self::group($itemGroups);

        foreach ($items as $item) {
            foreach ($_groups as $_items) {
                $groups[] = array_merge([$item], $_items);
            }
        }

        return $groups;
    }

    /**
     * 从一个集合中取多个元素得到一个组合，返回所有组合
     * @param array $sets
     * @param int $num
     * @return array
     */
    public static function groupByNum($sets, $num)
    {
        $groups = array();
        $n = count($sets);

        for ($i = 0; $i < $n; $i++) {
            if ($num > 1) {
                $_sets = $sets;
                array_splice($_sets, $i, 1);
                $_result = self::groupByNum($_sets, $num - 1);
                foreach ($_result as $group) {
                    $group = array_merge([$sets[$i]], $group);
                    $groups[] = $group;
                }
            } else {
                $groups[] = [$sets[$i]];
            }
        }

        return $groups;
    }

    /**
     * 获取整数的积因子
     * @param int $num
     * @param null|int $maxFactor 最大因子值
     * @param null|int $maxCount 最多因子数量
     * @return array|bool
     */
    public static function getFactors($num, $maxFactor = null, $maxCount = null)
    {
        $factors = array();
        if ($num == 1) return [1];

        while ($num > 1) {
            $found = false;
            $max = $maxFactor ? min($num, $maxFactor) : $num;
            for ($factor = 2; $factor <= $max; $factor++) {
                if ($num % $factor == 0) {
                    $factors[] = $factor;
                    $num = $num / $factor;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                if ($maxFactor && $maxFactor < $num) {
                    return false;
                }
                $factors[] = $num;
                break;
            }
        }

        if ($maxCount) {
            while (count($factors) > $maxCount) {
                rsort($factors, SORT_NUMERIC);
                $factor = array_pop($factors) * array_pop($factors);
                if ($maxFactor && $factor > $maxFactor) {
                    return false;
                }
                $factors[] = $factor;
            }
        }

        return $factors;
    }

    /**
     * 判断$group1是否是$group2的子集
     * @param $group1
     * @param $group2
     * @return bool
     */
    public static function isSubset($group1, $group2)
    {
        $counts = array_count_values($group2);

        foreach ($group1 as $val) {
            if (empty($counts[$val])) {
                return false;
            }
            $counts[$val]--;
        }

        return true;
    }

    /**
     * 计算方差
     * @param $sets
     */
    public static function variance($sets, $exp = 2, $avg = null)
    {
        if (!$sets) return 0;

        $count = count($sets);

        if ($avg === null) {
            $avg = array_sum($sets) / $count;
        }

        $variance = 0;

        foreach ($sets as $num) {
            $variance += pow($num - $avg, $exp);
        }

        $variance /= $count;

        return $variance;
    }

    /**
     * 计算标准差
     */
    public static function standardDev($sets, $avg = null)
    {
        $count = count($sets);

        if ($count <= 1) return 0;

        $variance = self::variance($sets, 2, $avg);
        $stdev = pow($variance * $count / ($count - 1), 0.5);

        return $stdev;
    }
}