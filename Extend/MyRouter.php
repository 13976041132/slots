<?php
/**
 * 路由器扩展
 */

namespace FF\Extend;

use FF\Framework\Core\FFRouter;
use FF\Library\Utils\Request;

class MyRouter extends FFRouter
{
    public function __construct()
    {
        $this->addRule($this->getRouteByMsgId());

        parent::__construct();
    }

    protected function getRouteByMsgId()
    {
        return function () {
            return Request::getRoute();
        };
    }
}