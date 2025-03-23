<?php

namespace FF\Bll;

use FF\Constants\MessageIds;
use FF\Factory\Bll;
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
            $messages[] = json_encode($this->makeData($info['triggerUid'], $info['messageId'], $info['time']), JSON_UNESCAPED_UNICODE);
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

    //加入俱乐部失败
    public function clubJoinFail($uid, $optUId, $clubName)
    {
        $data = $this->makeData($optUId, MessageIds::JOIN_CLUB_FAIL_NOTIFY, [$clubName]);
        $this->recordNotifyMsg($uid, $data);
    }

    public function clubJoinSuccess($uid, $optUId, $clubName)
    {
        $data = $this->makeData($optUId, MessageIds::JOIN_CLUB_SUCCESS_NOTIFY, [$clubName]);
        $this->recordNotifyMsg($uid, $data);
    }

    public function refuseJoinClub($uid, $optUId, $clubName)
    {
        $data = $this->makeData($optUId, MessageIds::REFUSE_JOIN_CLUB_NOTIFY, [$clubName]);
        $this->recordNotifyMsg($uid, $data);
    }

    public function clubMute($uid, $optUId, $status)
    {
        $messageId = $status == ClubBll::MUTE_STATUS_ACTIVE ? MessageIds::CLUB_MUTE_NOTIFY : MessageIds::CLUB_MUTE_CANCEL_NOTIFY;
        $data = $this->makeData($optUId, $messageId);
        $this->recordNotifyMsg($uid, $data);
    }

    public function kickOutClub($uid, $optUId)
    {
        $data = $this->makeData($optUId, MessageIds::CLUB_KICK_OUT_NOTIFY);
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

    public function pushNotifyMsg($uid, $optUId, $messageId, $content = [])
    {
        $data = $this->makeData($optUId, $messageId, $content);
        $this->recordNotifyMsg($uid, $data);
    }

    public function batchRecordNotifyMsg($uid, $groupData)
    {
        if (!$groupData) {
            return;
        }
        $key = Keys::bllMessageQueue($uid);
        Dao::redis()->rPush($key, ...$groupData);

        if (Dao::redis()->ttl($key) <= 3600) {
            Dao::redis()->expire($key, 3600 * 12);
        }
    }

    public function makeData($optUId, $messageId, $content = [])
    {
        return array(
            'uid' => $optUId,
            'msgId' => $messageId,
            'time' => time(),
            'content' => $content
        );
    }

    public function clubBroadcast($clubId, $optUid, $messageId, $content = [])
    {
        $members = Bll::club()->getClubMembers($clubId);
        if (!$members || count($members) == 1) {
            return;
        }
        //获取俱乐部玩家信息
        $pipe = Dao::redis()->pipeline();
        foreach ($members as $member) {
            if($member == $optUid) {
                continue;
            }
            $key = Bll::user()->getCacheKey($member, []);
            $pipe->exists($key);
        }
        $exists = $pipe->exec();
        $pipe1 = Dao::redis()->pipeline();
        $data = $this->makeData($optUid, $messageId, $content);
        foreach ($members as $inx => $_member) {
            if($_member == $optUid) {
                continue;
            }
            if (empty($exists[$inx])) {
                continue;
            }
            $key = Keys::bllMessageQueue($_member);
            $pipe1->rPush($key, json_encode($data, JSON_UNESCAPED_UNICODE));
            $pipe1->expire($key, 3600 * 12);
        }
        $pipe1->exec();
    }
}