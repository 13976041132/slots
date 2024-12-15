<?php

namespace FF\App\GameMain\Controller;

use FF\App\GameMain\Model\Main\UserBllRewardDataModel;
use FF\Constants\Exceptions;
use FF\Constants\MessageIds;
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

    public function inviteAward()
    {
        $uid = $this->getUid();
        $inviteeUid = $this->getParam('inviteeUid');
        $where = [
            'triggerUid' => $inviteeUid,
            'uid' => $uid,
            'messageId' => MessageIds::INVITED_BIND_AWARD_NOTIFY,
            'status' => UserBllRewardDataModel::STATUS_NON_AWARD
        ];
        $info = Model::userBllRewardData()->fetchOne($where);
        if (!$info) {
            FF::throwException(Exceptions::FAIL,'award fail');
        }
        $updateWhere = array_merge($where, ['updateTime' => $info['updateTime']]);
        $result = Model::userBllRewardData()->update(['status' => UserBllRewardDataModel::STATUS_AWARD], $updateWhere);
        if (!$result) {
            FF::throwException(Exceptions::FAIL, 'award fail');
        }
        return [];
    }
}