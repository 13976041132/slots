<?php

namespace FF\Bll;

use FF\Constants\MessageIds;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

class MessageNotifyBll
{
    const MESSAGE_IDS = [MessageIds::INVITED_BIND_AWARD_NOTIFY];
    public function clearQueueMessage($uid)
    {
        Dao::redis()->del(Keys::bllMessageQueue($uid));
    }
    public function loadRewardNotifyMessage($uid)
    {
        $where = [
            'uid' => $uid, 'expireTime' => ['>=', time()],
            'messageId' => ['in', self::MESSAGE_IDS], 'status' => 0
        ];
        $list = Model::userBllRewardData()->fetchAll($where, 'uid,triggerUid,messageId,time');
        $messages = [];
        foreach ($list as $info) {
            $messages[] = $this->makeData($info['triggerUid'], $info['messageId'], $info['time']);
        }
        $this->batchRecordNotifyMsg($uid, $messages);
    }
    public function addFriendRequest($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::ADD_FRIEND_REQUEST_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function accessFriend($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::ACCESS_FRIEND_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function refuseFriend($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::REFUSE_FRIEND_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function delFriend($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::DEL_FRIEND_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function receiveFriendCoins($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::RECEIVE_FRIEND_COINS_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }
    public function receiveFriendStamp($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function invited($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::INVITED_BIND_AWARD_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }
    public function receiveChatMsg($uid, $optUId, $content)
    {
        $data = $this->makeData($optUId, MessageIds::CHAT_MSG_RECEIVE_NOTIFY);
        $this->recordNotifyMsg($uid, $data);
    }

    public function recordNotifyMsg($uid, $data)
    {
        $key = Keys::bllMessageQueue($uid);
        Dao::redis()->rPush($key, json_encode($data, JSON_UNESCAPED_UNICODE));

        if (Dao::redis()->ttl($key) <= 3600) {
            Dao::redis()->expire($key, 3600 * 12);
        }
    }
    public function batchRecordNotifyMsg($uid, $groupData)
    {
        $key = Keys::bllMessageQueue($uid);
        Dao::redis()->rPush($key, ...$groupData);

        if (Dao::redis()->ttl($key) <= 3600) {
            Dao::redis()->expire($key, 3600 * 12);
        }
    }
    public function makeData($optUId, $messageId, $time = null)
    {
        return array(
            'uid' => $optUId,
            'msgId' => $messageId,
            'time' => $time ?: time(),
        );
    }
}