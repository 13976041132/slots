<?php
/**
 * Api控制器基类
 */

namespace FF\App\GameMain\Controller;

use Exception;
use FF\Constants\Exceptions;
use FF\Extend\MyController;
use FF\Factory\Bll;
use FF\Factory\Model;
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

    private $filterIgnoreRequestId = array(
        '/User/fetchRequestInfo', '/BllMessageController/*','/User/login'
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

        return false;
    }

    public function getUid()
    {
        return Bll::session()->get('uid');
    }
    public function afterResponse($resData, $error)
    {
        if ($this->isInFilter($this->filterIgnoreRequestId)) {
            return;
        }
        $controller = FF::getController();
        if (!$uid = $this->getUid()) {
            return;
        }
        $params = $controller ? $controller->getParams() : $_REQUEST;

        if(empty(Input::request('q')) || empty(Input::request('c'))) {
            return;
        }
        if (!FF::getRouter()->isValid()) {
            return;
        }

        if (!empty($params) || is_array($params)) {
            $params = json_encode($params);
        }
        $response = array('code' => 0, 'message' => 'success', 'data' => $resData);

        if ($error instanceof Exception) {
            $response = array('code' => $error->getCode(), 'message' => $error->getMessage(), 'data' => '');
        }

        $log = [
            'uid' => $uid,
            'request' => $params ?? '{}',
            'messageId' => (int)Input::request('c'),
            'requestId' => (string)Input::request('q'),
            'response' => json_encode($response),
            'requestTime' => time(),
        ];

        Model::userRequestLast()->insert($log, true);
    }
}