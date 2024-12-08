<?php

namespace FF\App\GameMain\Controller;

use FF\Constants\Exceptions;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;

class UserController extends BaseController
{
    public function dataReport()
    {
        $uid = $this->getUid();
        $data = $this->getParams();
        Bll::user()->updateUserInfo($uid, $data);

        return [];
    }
    public function fetchRequestInfo()
    {
        $uid = $this->getUid();
        $requestId = $this->getParam('requestId');
        $info = Model::userRequestLast()->getOneById($uid);

        if(!$info || $info['requestId'] != $requestId) {
            FF::throwException(Exceptions::FAIL);
        }

        return [
            'messageId' => $info['messageId'],
            'request' => json_decode($info['request'], true) ? : [],
            'response' => json_decode($info['response'], true) ?: [],
            'requestTime' => $info['requestTime'] ? date('Y-m-d H:i:s', $info['requestTime']) : '',
        ];
    }
}