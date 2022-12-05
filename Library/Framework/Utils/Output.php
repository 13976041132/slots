<?php
/**
 * 输出管理器
 */

namespace FF\Framework\Utils;

use FF\Framework\Core\FF;

class Output
{
    /**
     * 获取输出内容格式
     */
    public static function getFormat()
    {
        FF::getViewer()->getFormat();
    }

    /**
     * 设置输出内容格式
     * @param string $format
     */
    public static function setFormat($format)
    {
        FF::getViewer()->setFormat($format);
    }

    /**
     * 渲染页面
     * @param string $tpl
     * @param array $data
     */
    public static function display($tpl, $data = array())
    {
        FF::getViewer()->display($tpl, $data);
    }

    /**
     * 输出数据
     * @param $data
     * @param string $msg
     */
    public static function data($data, $msg = '')
    {
        FF::getViewer()->data($data, $msg);
    }

    /**
     * 输出错误-异常
     * @param $code
     * @param string $msg
     */
    public static function error($code, $msg = '')
    {
        FF::getViewer()->error($code, $msg);
    }
}