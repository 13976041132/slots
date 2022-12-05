<?php
/**
 * 常用函数库
 */

namespace FF\Library\Utils;

use FF\Framework\Utils\Config;

class Utils
{
    /**
     * 根据给定概率得到是否命中
     * @param $rate
     * @return bool
     */
    public static function isHitByRate($rate)
    {
        if ((float)$rate >= 1) return true;
        if ((float)$rate <= 0) return false;

        $decimals = self::getDecimals($rate);

        //随机范围上限
        $maxNum = pow(10, $decimals);

        //命中范围上限
        $maxHitNum = (int)((float)$rate * $maxNum);

        return mt_rand(1, $maxNum) <= $maxHitNum;
    }

    /**
     * 根据给定概率表得到随机结果
     * 返回命中元素的key
     * @param $rates
     * @return int|string
     */
    public static function randByRates($rates)
    {
        //计算最大小数位数
        $decimals = 0;
        foreach ($rates as $key => $val) {
            $_decimals = self::getDecimals($val);
            $decimals = max($decimals, $_decimals);
        }
        $scale = $decimals ? pow(10, $decimals) : 1;

        $max = $key = 0;
        foreach ($rates as $key => $val) {
            if ((float)$val <= 0) {
                unset($rates[$key]);
                continue;
            }
            $max += (int)((float)$val * $scale);
            $rates[$key] = $max;
        }

        if (!$rates) {
            throw new \Exception('rates set is empty');
        }

        $rnd = mt_rand(1, $max);
        foreach ($rates as $key => $val) {
            if ($rnd <= $val) break;
        }

        return $key;
    }

    /**
     * 计算小数位数
     * 太小的小数php用科学计数法表示(这里不考虑大整数)
     * 例如0.0000234 = 2.34E-5
     * @param $num
     * @return int
     */
    public static function getDecimals($num)
    {
        if (!is_numeric($num)) return 0;
        if ($num == floor($num)) return 0;

        if (strpos((string)$num, 'E-')) {
            $nums = explode('E-', (string)$num);
            $decimals = (int)$nums[1];
            if ($nums[0] != (int)$nums[0]) {
                $decimals += strlen(explode('.', $nums[0])[1]);
            }
        } else {
            $decimals = strlen(explode('.', (string)$num)[1]);
        }

        return $decimals;
    }

    /**
     * 获取指定长度的随机字符串
     * @param $len
     * @return string
     */
    public static function getRandChars($len)
    {
        $chars = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
        );

        shuffle($chars);

        $chars = array_slice($chars, 0, $len);

        return implode('', $chars);
    }

    /**
     * 获取文档注释(只取第一行注释)
     * @param \ReflectionClass | \ReflectionClassConstant | \ReflectionMethod $ref
     * @return string
     */
    public static function getDocComment($ref, $index = 1)
    {
        $comment = $ref->getDocComment();
        if ($comment === false) {
            return '';
        }

        $comments = explode("\n", $comment);
        $comments = array_filter($comments);
        $comment = substr(trim($comments[$index]), 1);

        return $comment;
    }

    /**
     * 数据类型转换
     * @param $data
     * @param $format
     * @return bool|float|int|string
     */
    public static function dataFormat($data, $format)
    {
        switch ($format) {
            case 'int':
            case 'double':
                $data = (int)$data;
                break;
            case 'float':
                $data = (float)$data;
                break;
            case 'string':
                $data = (string)$data;
                break;
            case 'bool':
                $data = (bool)$data;
                break;
            default:
                break;
        }

        return $data;
    }

    /**
     * 获取周内某天的时间
     * @param string $day Monday|...
     * @param int $weekOffset 周数偏移
     * @param string $spacer 日期间隔符
     * @return string
     */
    public static function getWeekDay($day, $weekOffset = 0, $spacer = '-')
    {
        $day = ucfirst(strtolower($day));

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $dayNumber = (int)array_flip($days)[$day] + 1;
        $dayOffset = $dayNumber - date('N');

        $time = strtotime('today') + $dayOffset * 86400 + $weekOffset * 7 * 86400;

        return date("Y{$spacer}m{$spacer}d", $time);
    }

    /**
     * 从csv文件加载数据
     * @param string $csvFile
     * @return array
     */
    public static function loadCsv($csvFile)
    {
        $reader = new CsvReader($csvFile);

        return $reader->readAll();
    }

    /**
     * 递归合并两个数组，保留键名
     */
    public static function arrayMerge($array1, $array2)
    {
        foreach ($array2 as $k => $v) {
            if (isset($array1[$k]) && is_array($array1[$k]) && is_array($v)) {
                $array1[$k] = self::arrayMerge($array1[$k], $v);
            } else {
                $array1[$k] = $v;
            }
        }

        return $array1;
    }

    /**
     * 判断值是否与目标匹配
     */
    public static function isValueMatched($value, $target)
    {
        if ($target === null) {
            return true;
        }

        if ($value === null) {
            $value = 0;
        }

        if (is_string($target) && !is_numeric($target)) {
            $symbol = substr($target, -1);
            if ($symbol == '+') {
                $target = [substr($target, 0, -1), 0];
            } elseif ($symbol == '-') {
                $target = [0, substr($target, 0, -1)];
            } elseif (stripos($target, '-')) {
                $target = explode('-', $target, 2);
            }
        }

        if (is_array($target)) { //左闭右开
            if (!$target) return true;
            $value = (float)$value;
            $min = (float)$target[0];
            $max = (float)$target[1];
            if ($value < $min) return false;
            if ($max && $value >= $max) return false;
        } elseif ($value != $target) {
            return false;
        }

        return true;
    }

    /**
     * 根据值和值的范围配置，匹配出另外一个值
     */
    public static function matchValueByRect($value, $rects)
    {
        if (!$rects || !is_array($rects)) {
            return null;
        }

        foreach ($rects as $rect => $v) {
            if (is_string($rect) && in_array(substr($rect, -1), ['+', '-'])) {
                if (substr($rect, -1) == '+') {
                    $target = [substr($rect, 0, -1), 0];
                } else {
                    $target = [0, substr($rect, 0, -1)];
                }
            } else {
                $target = is_numeric($rect) ? $rect : explode('-', $rect);
            }
            if (self::isValueMatched($value, $target)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * 数值格式化，分区间取整
     */
    public static function valueFormat($value)
    {
        $valueRules = Config::get('common/general', 'valueRule', false);
        if (!$valueRules) return $value;

        foreach ($valueRules as $rect => $v) {
            $rect = json_decode($rect, true);
            if ($value >= $rect[0] && (!$rect[1] || $value < $rect[1])) {
                $value = ceil($value / $v) * $v;
                break;
            }
        }

        return $value;
    }

    /**
     * 返回某天所属的自然周开始和结束的时间戳
     * @return array
     */
    public static function getWeekTime($timestamp = null)
    {
        $timestamp = $timestamp ?: time();
        return [
            strtotime(date('Y-m-d', strtotime("this week Monday", $timestamp))),
            strtotime(date('Y-m-d', strtotime("this week Sunday", $timestamp))) + 24 * 3600 - 1
        ];
    }

    /**
     * 判断某个日期是否是当天
     * @return bool
     */
    public static function isToday($timestamp)
    {
        $todayStamp = [
            mktime(0, 0, 0, date('m'), date('d'), date('Y')),
            mktime(23, 59, 59, date('m'), date('d'), date('Y'))
        ];

        return $timestamp >= $todayStamp[0] && $timestamp <= $todayStamp[1];
    }

    /**
     * 判断某个日期是否是在本自然周内
     * @return bool
     */
    public static function isCurWeek($timestamp)
    {
        $weekStamp = Utils::getWeekTime();
        return $timestamp >= $weekStamp[0] && $timestamp <= $weekStamp[1];
    }

    /**
     * 检查多维数组是否为空
     */
    public static function arrayIsNull($arr)
    {
        $isNull = true;
        if (!$arr) return $isNull;
        foreach ($arr as $val) {
            if (is_array($val)) {
                $isNull = self::arrayIsNull($val);
            } elseif (!empty($val)) {
                $isNull = false;
            }
        }
        return $isNull;
    }

    /**
     * 整型数组自动填充，用于获取配置区间ID
     */
    public static function arrayRange($arr)
    {
        if (!$arr) return [];

        $arrRange = [];
        foreach ($arr as $val) {
            if (is_string($val) && stristr($val, '-') !== false) {
                $tempVal = explode('-', $val);
                $arrRange = array_merge($arrRange, range($tempVal[0], $tempVal[1]));
                continue;
            }
            $arrRange[] = $val;
        }
        return $arrRange;
    }

}