<?php

namespace FF\App\GameMain\Controller;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

class BllMessageController extends BaseController
{
    //获取业务信息
    public function fetchMessageList()
    {
        $uid = $this->getUid();
        Bll::user()->updateUserInfo($uid, ['lastOnlineTime' => time()]);
        //通过队列获取玩家相关业务推送信息
        $key = Keys::bllMessageQueue($uid);
        $result = Dao::redis()->lRange($key, 0, 200);
        $list = [];
        foreach ($result as $row) {
            $list[] = json_decode($row, true);
        }
        return $list;
    }

    //消息统计列表
    public function fetchMsgStatInfo()
    {
        $uid = $this->getUid();
        $unreadCnt = Bll::friends()->getUnreadCount($uid);
        $coinTimes = Bll::friends()->getReceiveFriendGiftCount($uid, MessageIds::RECEIVE_FRIEND_COINS_NOTIFY);
        $stampTimes = Bll::friends()->getReceiveFriendGiftCount($uid, MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY);
        return [
            'unreadMsgCnt' => $unreadCnt, //未读的消息数量
            'receiveFriendCoinMsgCnt' => $coinTimes, //收到赠送金币消息数量
            'receiveFriendStampMsgCnt' => $stampTimes,//收到赠送邮票消息数量
            'lastRequestId' => Model::userRequestLast()->getRequestId($uid),//最后请求的id
        ];
    }
}