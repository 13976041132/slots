<?php

namespace FF\App\GameMain\Controller;

use FF\App\GameMain\Model\Main\UserBllRewardDataModel;
use FF\Bll\ClubBll;
use FF\Constants\Exceptions;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use GPBClass\Enum\RET;

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

        if (!$info || $info['requestId'] != $requestId) {
            FF::throwException(Exceptions::FAILED);
        }

        return [
            'messageId' => $info['messageId'],
            'request' => json_decode($info['request'], true) ?: [],
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
            FF::throwException(Exceptions::FAILED, 'award fail');
        }
        $updateWhere = array_merge($where, ['updateTime' => $info['updateTime']]);
        $result = Model::userBllRewardData()->update(['status' => UserBllRewardDataModel::STATUS_AWARD], $updateWhere);
        if (!$result) {
            FF::throwException(Exceptions::FAILED, 'award fail');
        }
        return [];
    }

    //玩家登录
    public function login()
    {
        $deviceId = $this->getParam('deviceId');
        $uid = $this->getParam('uid');
        $userInfo = Bll::user()->getUserInfo($uid);
        if (!$userInfo['uid'] || $userInfo['deviceId'] != $deviceId) {
            Log::error($uid, 'user.log');
            FF::throwException(Exceptions::RET_ACCOUNT_NOT_EXIST);
        }
        $sessionData = array('uid' => $uid, 'deviceId' => $deviceId);
        $sessionId = Bll::session()->create($uid, $sessionData);
        Bll::user()->clearSession($uid);
        Bll::user()->setSessionId($uid, $sessionId);
        //记录当前玩家登录过
        Model::userDailyFirstLoginLog()->record($uid);
        Bll::user()->resetCacheData($uid);
        Bll::user()->updateUserInfo($uid, ['lastOnlineTime' => time()]);
        Bll::messageNotify()->clearQueueMessage($uid);
        Bll::messageNotify()->loadRewardNotifyMessage($uid);
        $msgStatData = Bll::user()->fetchMsgStatInfo($uid);
        Bll::userRequestLast()->clean($uid);
        return array_merge(
            $msgStatData,
            [
                'token' => $sessionId,
                'lastRequestId' => Bll::userRequestLast()->getRequestId(),
                'clubId' => Bll::club()->getClubIdByUid($uid),
                'secretKey' => Bll::userRequestLast()->touchSecretKey(false, true),
                'friendList' => Bll::friends()->getFriendsInfo($uid),
            ]
        );
    }
    //查询玩家的数据
    public function fetchUserInfo()
    {
        $uid = $this->getUid();
        $tuid = $this->getParam('tuid');
        $userInfo = Model::user()->getOneById($tuid);
        if (!$userInfo) {
            FF::throwException(Exceptions::RET_ACCOUNT_NOT_EXIST);
        }
        $info = array(
            'uid' => $userInfo['uid'],
            'coin' => $userInfo['coin'] ?? 0,
            'name' => $userInfo['name'],
            'level' => $userInfo['level'],
            'headId' => $userInfo['headId'] ?? 0,
            'headFrameId' => $userInfo['headFrameId'] ?? 0,
            'vipLevel' => $userInfo['vipLevel'] ?? 0,
            'region' => $userInfo['region'] ?? 0,
            'facebookId' => $userInfo['facebookId'] ?? 0,
            'friendFlag' => Bll::friends()->isMyFriend($uid, $tuid),
            'achieveInfo' => $userInfo['achieve'] ?? [],
            'clubInfo' => [],
        );

        $userClubInfo = Bll::clubUser()->getInfo($tuid);
        $clubInfo = [];
        if (!empty($userClubInfo['clubId'])) {
            $clubInfo = Bll::clubCache()->getInfo($userClubInfo['clubId'], 'headId,clubName,clubId,dan');
        }
        if(!$clubInfo) {
            return $info;
        }
        //todo
        $roleNames = [];
        $clubInfo['roleName'] = implode(',', $roleNames);
        $clubInfo['muteStatus'] = $userClubInfo['muteStatus'] ?? 0;
        $clubInfo['points'] = $userClubInfo['points'] ?? 0;
        $info['clubInfo'] = $clubInfo;
        return $info;
    }
}