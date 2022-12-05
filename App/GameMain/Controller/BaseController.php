<?php
/**
 * Api控制器基类
 */

namespace FF\App\GameMain\Controller;

use FF\Extend\MyController;
use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Input;
use FF\Framework\Utils\Log;
use FF\Framework\Utils\Output;
use FF\Library\Utils\Request;
use FF\Service\Lib\Service;
use GPBClass\Enum\RET;

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

        if (!$this->checkSession()) {
            FF::throwException(RET::RET_SESSION_INVALID);
        }

        if (!$this->checkVersion()) {
            FF::throwException(RET::RET_VERSION_TOO_OLD);
        }

        if (!$this->checkCallFrequency()) {
            FF::throwException(RET::RET_REQUEST_TO_FREQUENT);
        }
    }

    private function checkMaintain()
    {
        if (Config::get('core', 'in_maintain')) {
            $message = 'We are having a server maintenance, please try again later.';
            FF::throwException(RET::RET_SYSTEM_MAINTAIN, $message);
        }
    }

    private function checkSession()
    {
        $sessionId = Input::request('s');
        Bll::session()->setSessionId($sessionId);

        if ($this->isInFilter($this->filterNotNeedLogin)) {
            return true;
        }

        $session = Bll::session()->getSessionData();
        if (!$session) {
            Log::error($sessionId, 'session.log');
        }

        return $session ? true : false;
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

        $nowTime = time();
        $lastTime = Bll::session()->get($route);
        if (!$lastTime || $nowTime - $lastTime >= $this->apiCallFreqLimits[$route]) {
            Bll::session()->save(array($route => $nowTime));
            return true;
        }

        Log::info([$route, $lastTime, $nowTime], 'call.log');

        return false;
    }

    public function getUid()
    {
        return Bll::session()->get('uid');
    }

    public function getPlatform()
    {
        return Bll::session()->get('platform');
    }

    public function getVersion()
    {
        return Bll::session()->get('version');
    }

    public function getMachineId()
    {
        $machineId = Bll::session()->get('machineId');
        if (!$machineId) {
            $machineId = $this->getParam('machineId', false);
        }
        return $machineId;
    }

    public function getMachineObj()
    {
        $uid = $this->getUid();
        $machineId = $this->getMachineId();

        if (!$uid || !$machineId) {
            throw new \Exception('', Code::PARAMS_INVALID);
        }

        $machineObj = Bll::machine()->getMachineInstance($uid, $machineId);

        return $machineObj;
    }
}