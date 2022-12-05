<?php
/**
 * API测试工具
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;
use GPBClass\Enum\MSG_ID;
use GPBClass\Enum\RET;

if (FF::isProduct()) {
    header('HTTP/1.1 404 Not Found');
    exit(0);
}

class ApiTestController extends BaseController
{
    public function index()
    {
        $msgIdClass = '\GPBClass\Enum\MSG_ID';
        $classRef = new \ReflectionClass($msgIdClass);
        $msgIds = $classRef->getConstants();
        unset($msgIds['MSG_NULL']);

        foreach ($msgIds as $msgCode => $msgId) {
            $desc = substr($msgCode, 4);
            if (class_exists('ReflectionClassConstant', false)) {
                $constRef = new \ReflectionClassConstant($msgIdClass, $msgCode);
                $desc = Utils::getDocComment($constRef);
            }
            $msgIds[$msgCode] = array(
                'desc' => $desc,
                'value' => $msgId
            );
        }

        $data['msgIds'] = $msgIds;

        $this->display('api-test.html', $data);
    }

    public function getApiDetail()
    {
        $msgId = (int)$this->getParam('msgId');

        $route = Config::get('routes', $msgId);
        if (!$route) {
            FF::throwException(Code::PARAMS_INVALID, "无路由配置[MSG_ID={$msgId}]");
        }

        $proto = $route[1];
        $protoClass = "\\GPBClass\\Message\\{$proto}_Req";
        $classRef = new \ReflectionClass($protoClass);
        $methods = $classRef->getMethods(\ReflectionMethod::IS_PUBLIC);

        $params = array();
        foreach ($methods as $methodRef) {
            $methodName = $methodRef->getName();
            if (substr($methodName, 0, 3) == 'get') {
                $desc = Utils::getDocComment($methodRef);
                $params[] = array(
                    'key' => lcfirst(substr($methodName, 3)),
                    'desc' => $desc
                );
            }
        }

        $desc = null;
        $msgIdClass = '\GPBClass\Enum\MSG_ID';
        $classRef = new \ReflectionClass($msgIdClass);
        $consts = $classRef->getConstants();
        $msgCode = array_search($msgId, $consts);
        if (class_exists('ReflectionClassConstant', false)) {
            $constRef = new \ReflectionClassConstant($msgIdClass, $msgCode);
            $desc = Utils::getDocComment($constRef);
        }

        return array(
            'msgId' => $msgId,
            'msgCode' => $msgCode,
            'desc' => $desc,
            'route' => $route[0],
            'proto' => $route[1],
            'params' => $params
        );
    }

    public function getResponse()
    {
        $msgId = (int)$this->getParam('msgId');
        $params = $this->getParam('params', false, array());

        $route = Config::get('routes', $msgId);
        if (!$route) {
            FF::throwException(Code::PARAMS_INVALID, "无路由配置[MSG_ID={$msgId}]");
        }

        $sessionId = '';
        if ($msgId != MSG_ID::MSG_USER_LOGIN) {
            $sessionId = $this->getSessionId();
        }

        foreach ($params as $key => &$val) {
            if (is_empty($val)) {
                unset($params[$key]);
            } elseif (in_array(strtolower($val), ['true', 'false'])) {
                $val = strtolower($val) == 'true';
            } else {
                $array = json_decode($val, true);
                if ($array !== null) {
                    $val = $array;
                }
            }
        }

        $proto = $route[1];
        $protoClass = "\\GPBClass\\Message\\{$proto}_Req";
        /**@var $pbObject \Google\Protobuf\Internal\Message */
        $pbObject = new $protoClass();
        $pbObject->mergeFromJsonString(json_encode($params));
        $msg = $pbObject->serializeToString();

        $data = array(
            'c' => $msgId, 'k' => base64_encode($msg), 's' => $sessionId, 'f' => 'pbuf'
        );

        $time = _microtime();
        $ch = curl_init(BASE_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        $response = json_decode($result, true);
        $cost = _microtime() - $time;
        Log::info([$cost, $response], 'api-test.log');

        if (!$response) {
            Log::error($result);
            $response = array('code' => RET::FAILED, 'message' => 'API response invalid');
            echo json_encode($response);
            exit(0);
        }

        if (!$response['code']) {
            $protoClass = "\\GPBClass\\Message\\{$proto}_Res";
            $pbObject = new $protoClass();
            $data = base64_decode($response['data']);
            $pbObject->mergeFromString($data);
            $data = $pbObject->serializeToJsonString();
            $response['data'] = json_decode($data, true);
        } elseif (!$response['message']) {
            $codeClass = '\GPBClass\Enum\RET';
            $classRef = new \ReflectionClass($codeClass);
            $codes = $classRef->getConstants();
            $error = '';
            foreach ($codes as $codeName => $code) {
                if ($code == $response['code']) {
                    $error = $codeName;
                    if (substr($error, 0, 4) == 'RET_') {
                        $error = substr($error, 4);
                    }
                    break;
                }
            }
            if ($error) {
                $response['message'] = json_encode(array('error' => $error));
            }
        }

        echo json_encode($response);
        exit(0);
    }

    protected function getSessionId()
    {
        $uid = (int)$this->getParam('uid', false, 10000);
        $sessionId = Bll::user()->getSessionId($uid);

        if ($sessionId) {
            Bll::session()->setSessionId($sessionId);
            if (!Bll::session()->getSessionData()) {
                $sessionId = '';
            }
        }

        if (!$sessionId) {
            $version = Bll::version()->getNewestVersion(0);
            $sessionData = array('uid' => $uid, 'platform' => 0, 'version' => $version);
            $sessionId = Bll::session()->create($uid, $sessionData);
            Bll::user()->setSessionId($uid, $sessionId);
        }

        return $sessionId;
    }
}