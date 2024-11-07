<?php
/**
 * Api控制器基类
 */

namespace FF\App\GameMain\Controller;

use FF\Constants\Exceptions;
use FF\Extend\MyController;
use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;
use FF\Framework\Utils\Output;
use FF\Library\Utils\Request;

class BaseController extends MyController
{
    private $filterNotNeedLogin = array(
        '/User/login', '/ApiTest/*', '/Version/*','/Public/eventReport'
    );

    private $apiCallFreqLimits = array();

    public function init()
    {
        Output::setFormat(Request::getFormat());
        $this->setParams(Request::getMessage());

        $this->checkMaintain();

        if (!$this->checkUser()) {
            FF::throwException(Exceptions::RET_USER_INVALID);
        }

        if (!$this->checkVersion()) {
            FF::throwException(Exceptions::RET_VERSION_TOO_OLD);
        }

        if (!$this->checkCallFrequency()) {
            FF::throwException(Exceptions::RET_REQUEST_TO_FREQUENT);
        }
    }

    private function checkMaintain()
    {
        if (Config::get('core', 'in_maintain')) {
            $message = 'We are having a server maintenance, please try again later.';
            FF::throwException(Exceptions::RET_SYSTEM_MAINTAIN, $message);
        }
    }

    private function checkUser()
    {
        $uid = (int)Input::request('u');
        $deviceId = (string)Input::request('d');
        if ($this->isInFilter($this->filterNotNeedLogin)) {
            return true;
        }

        $userInfo = Bll::user()->getUserInfo($uid);
        if (!$userInfo['uid'] || $userInfo['deviceId'] != $deviceId) {
            Log::error($uid, 'user.log');
            return false;
        }
        Bll::loginUser()->setUserInfo($userInfo);
        return true;
    }

    private function checkVersion()
    {
        return true;
    }

    private function checkCallFrequency()
    {
        $route = FF::getRouter()->getRoute();

        if (empty($this->apiCallFreqLimits[$route])) {
            return true;
        }

        return false;
    }

    public function getUid()
    {
        return Bll::loginUser()->get('uid');
    }
}