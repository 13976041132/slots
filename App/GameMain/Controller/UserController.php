<?php

namespace FF\App\GameMain\Controller;

use FF\App\GameMain\Model\Main\UserBllRewardDataModel;
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

        $unreadCnt = Bll::friends()->getUnreadCount($uid);
        $coinTimes = Bll::friends()->getReceiveFriendGiftCount($uid, MessageIds::RECEIVE_FRIEND_COINS_NOTIFY);
        $stampTimes = Bll::friends()->getReceiveFriendGiftCount($uid, MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY);
        return [
            'unreadMsgCnt' => $unreadCnt, //未读的消息数量
            'receiveFriendCoinMsgCnt' => $coinTimes, //收到赠送金币消息数量
            'receiveFriendStampMsgCnt' => $stampTimes,//收到赠送邮票消息数量
            'lastRequestId' => Model::userRequestLast()->getRequestId($uid),//最后请求的id
            'token' => $sessionId
        ];
    }
}