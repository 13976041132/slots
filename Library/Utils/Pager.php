<?php
/**
 * 分页器
 */

namespace FF\Library\Utils;

class Pager
{
    public $total;
    public $limit;
    public $limits;
    public $page;
    public $url;

    public function __construct($options)
    {
        $this->total = (int)$options['total'];
        $this->limit = (int)$options['limit'];
        $this->limits = [10, 15, 20, 25, 30, 50, 100];
        $this->page = (int)$options['page'];
        $url = isset($options['url']) ? $options['url'] : '';
        $this->setUrl($url);
    }

    public function setLimits($limits)
    {
        $this->limits = $limits;
    }

    public function setUrl($url)
    {
        if (!$url) {
            $url = BASE_URL . $_SERVER['REQUEST_URI'];
        }
        $urlInfo = parse_url($url);
        parse_str($urlInfo['query'], $params);
        unset($params['page']);
        unset($params['limit']);
        unset($params['_']);
        $queryString = http_build_query($params);
        $host = $urlInfo['scheme'] . '://' . $urlInfo['host'];
        if ($urlInfo['port'] && $urlInfo['port'] != 80) {
            $host .= ':' . $urlInfo['port'];
        }
        if ($queryString) {
            $url = $host . $urlInfo['path'] . '?' . $queryString;
        } else {
            $url = $host . $urlInfo['path'];
        }
        $this->url = $url;
    }

    public function makeUrl($page, $limit = null)
    {
        $limit = $limit !== null ? $limit : $this->limit;
        $joiner = strpos($this->url, '?') === false ? '?' : '&';
        if ($limit === '') {
            return $this->url . $joiner . 'page=' . $page . '&limit=' . $limit;
        } else {
            return $this->url . $joiner . 'limit=' . $limit . '&page=' . $page;
        }
    }

    public function display($num = 7)
    {
        if (!$this->total) return;

        $pageMax = ceil($this->total / $this->limit);

        $this->page = max(1, min($this->page, $pageMax));
        $start = max(1, $this->page - floor($num / 2));
        $end = min($pageMax, $start + $num - 1);
        if ($end == $pageMax) $start = max(1, $pageMax - $num + 1);

        $html = '<div class="pager">';
        $html .= '<ul class="pager-list">';
        //首页、上一页
        if ($this->page > 1) {
            $html .= '<li><a href="' . $this->makeUrl(1) . '">&lt;&lt;</a></li>';
            $html .= '<li><a href="' . $this->makeUrl($this->page - 1) . '">&lt;</a></li>';
        } else {
            $html .= '<li class="pager-disable"><span>&lt;&lt;</span></li>';
            $html .= '<li class="pager-disable"><span>&lt;</span></li>';
        }
        //当前页、第*页
        for ($i = $start; $i <= $end; $i++) {
            if ($i != $this->page) {
                $html .= '<li><a href="' . $this->makeUrl($i) . '">' . $i . '</a></li>';
            } else {
                $html .= '<li class="pager-active"><span>' . $i . '</span></li>';
            }
        }
        //下一页、最后一页
        if ($this->page < $pageMax) {
            $html .= '<li><a href="' . $this->makeUrl($this->page + 1) . '">&gt;</a></li>';
            $html .= '<li><a href="' . $this->makeUrl($pageMax) . '">&gt;&gt;</a></li>';
        } else {
            $html .= '<li class="pager-disable"><span>&gt;</span></li>';
            $html .= '<li class="pager-disable"><span>&gt;&gt;</span></li>';
        }
        $html .= '</ul>';
        //跳转至第*页
        $onInput = "$(this).parent().find('.pager-to-btn').attr('href', '" . $this->makeUrl('') . "' + $(this).val())";
        $html .= '<div class="pager-jump">';
        $html .= '<span>跳转至第</span>';
        $html .= '<input style="text" class="pager-to-num" onkeyup="' . $onInput . '">';
        $html .= '<span>页</span>';
        $html .= '<a href="" class="pager-to-btn">Go</a>';
        $html .= '</div>';
        //数据统计
        $options = '';
        foreach ($this->limits as $v) {
            $options .= '<option value="' . $v . '">' . $v . '</option>';
        }
        $onChange = "$(this).parents('.pager').find('.pager-to-btn').attr('href', '" . $this->makeUrl(1, '') . "' + $(this).val()).click()";
        $selector = '<select class="pager-limit-num" data-value="' . $this->limit . '" onchange="' . $onChange . '">' . $options . '</select>';
        $html .= '<div class="pager-analysis">';
        $html .= '<span>总计 ' . $this->total . ' 条记录，</span>';
        $html .= '<span>每页 <a href="javascript:;" onclick="$(this).hide();$(this).next().show();">' . $this->limit . '</a>' . $selector . ' 条，</span>';
        $html .= '<span>共 ' . $pageMax . ' 页</span>';
        $html .= '</div>';
        $html .= '</div>';
        echo $html;
    }
}