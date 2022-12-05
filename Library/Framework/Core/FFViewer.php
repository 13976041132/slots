<?php
/**
 * 视图器
 */

namespace FF\Framework\Core;

use FF\Framework\Common\Code;
use FF\Framework\Common\Format;

class FFViewer
{
    private $renders = array();
    private $format = Format::JSON;

    public function __construct()
    {
        $this->setRender(Format::JSON, function ($data) {
            if (!is_cli()) {
                header("Content-type: application/json; charset=utf-8");
            }
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        });
        $this->setRender(Format::HTML, function ($data) {
            if (!is_cli()) {
                header("Content-type: text/html; charset=utf-8");
            }
            FF::getViewer()->tplRendering($data[0], $data[1]);
        });
        $this->initRenders();
    }

    /**
     * 初始化渲染器
     */
    protected function initRenders()
    {
        //To override
    }

    /**
     * 设置渲染器
     * @param string $format
     * @param callable $render
     */
    protected function setRender($format, $render)
    {
        $this->renders[$format] = $render;
    }

    /**
     * 获取渲染器
     * @param string $format
     * @return callable|null
     */
    protected function getRender($format)
    {
        if (!$format) return null;

        if (isset($this->renders[$format]) && is_callable($this->renders[$format])) {
            return $this->renders[$format];
        } else {
            return null;
        }
    }

    /**
     * 清除渲染器
     */
    protected function clearRenders()
    {
        $this->renders = array();
    }

    /**
     * 获取渲染格式
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * 设置渲染格式
     * @param string $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * 渲染模板 - 需要重写
     * @param string $tpl
     * @param array $data
     */
    protected function tplRendering($tpl, $data)
    {
        //To override
    }

    /**
     * 按指定格式输出内容
     * @param array $data
     * @param string $format
     * @throws \Exception
     */
    public function output($data, $format = null)
    {
        $format = $format ? $format : $this->format;

        if (!$render = $this->getRender($format)) {
            FF::throwException(Code::SYSTEM_ERROR, "Render for $format is not exists!");
        }

        call_user_func($render, $data);

        exit(0);
    }

    /**
     * 按协议结构输出数据
     * @param array $data
     * @param string $msg
     * @throws \Exception
     */
    public function data($data, $msg = '')
    {
        $output = array('code' => Code::SUCCESS, 'message' => $msg, 'data' => $data);

        $this->output($output);
    }

    /**
     * 按协议结构输出错误
     * @param int $code
     * @param string $msg
     * @throws \Exception
     */
    public function error($code, $msg = '')
    {
        $output = array('code' => $code, 'message' => $msg, 'data' => null);

        $this->output($output);
    }

    /**
     * 输出页面内容
     * @param string $tpl
     * @param array $data
     * @throws \Exception
     */
    public function display($tpl, $data = array())
    {
        $this->output([$tpl, $data], Format::HTML);
    }
}