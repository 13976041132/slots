<?php
/**
 * 字符串工具类
 */

namespace FF\Framework\Utils;

class Str
{
    /**
     * 判断字符串是否包含指定的字串(批量)
     * @param $haystack
     * @param $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 百分比字符串转为数字
     * @param $str
     * @return float
     */
    public static function percent2num($str)
    {
        if (is_numeric($str)) {
            return (float)$str;
        } elseif (substr($str, -1) == '%') {
            $num = (float)substr($str, 0, -1);
            return $num / 100;
        } else {
            return 0;
        }
    }
}