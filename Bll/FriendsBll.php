<?php
/**
 * 好友业务逻辑
 * 目前只有好友关系处理
 */

namespace FF\Bll;

use FF\Constants\Exceptions;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;
use Exception;

class FriendsBll
{
    const FRIEND_GROUP_WITHIN_THREE_DAYS = 1;
    const FRIEND_GROUP_WITHOUT_THREE_DAYS = 2;
    // 好友申请场景
    const REQUEST_SCENE_DEFAULT = 0; // 默认
    const REQUEST_SCENE_SEARCH = 1; // 精确搜索
    const REQUEST_SCENE_SUGGEST = 2; // 推荐

    /**
     * 通过 Uid 获取用户好友列表
     * @param $uid
     */
    public function getFriends($uid)
    {
        $key = Keys::friends($uid);
        $friends = Dao::redis()->sMembers($key);

        // 检查数据
        if (empty($friends)) {
            $friends = $this->renewFriends($uid);
        }

        return $friends;
    }

    public function isMyFriend($uid, $tuid)
    {
        $friends = $this->getFriends($uid);
        return in_array($tuid, $friends);
    }

    /**
     * 通过 Uid 获取用户好友列表，包含好友详情信息
     */
    public function getFriendsInfo($uid)
    {
        $uids = $this->getFriends($uid);

        if (!$uids) return [];

        $coinKey = keys::sentFriendCoins($uid, 'send');
        $stampKey = keys::sentFriendStamp($uid, 'send');
        $userInfos = $this->formatUserInfo($uids);
        $coinList = Dao::redis()->hGetAll($coinKey);
        $stampList = Dao::redis()->hGetAll($stampKey);

        $friends = Bll::friendCache()->getFriendList($uid, $uids, 'fuid,unReadCnt,givingGiftTimes,receiveGiftTimes');

        foreach ($userInfos as $fuid => &$userInfo) {
            $uuid = Bll::friendCache()->makeUUID($uid,$fuid);
            // 是否能够发送免费金币
            $userInfo['stampGivingAble'] = !empty($stampList[$fuid]) ? 0 : 1;
            $userInfo['coinGivingAble'] = !empty($coinList[$fuid]) ? 0 : 1;
            $userInfo['unreadCnt'] = $friends[$uuid]['unReadCnt'] ?? 0;
            if (Bll::chatLog()->getLastChatTime($uid, $fuid)) {
                $userInfo['group'] = self::FRIEND_GROUP_WITHIN_THREE_DAYS;
                continue;
            }

            if (!empty($friends[$uuid]['givingGiftTimes']) && !empty($friends[$uuid]['receiveGiftTimes'])) {
                $userInfo['group'] = self::FRIEND_GROUP_WITHIN_THREE_DAYS;
                continue;
            }
            $userInfo['group'] = self::FRIEND_GROUP_WITHOUT_THREE_DAYS;
        }

        return $userInfos;
    }

    /**
     * 添加好友关系
     */
    public function addFriend($uid, $fUid)
    {
        // 好友申请
        Model::friendsRequests()->acceptFriendRequest($uid, $fUid);
        $this->renewRequestFriends($uid);
        $this->renewRequestFriends($fUid);

        // 更新结果
        $result = Bll::friendCache()->addFriend($uid, $fUid);

        // 更新redis
        if ($result > 0) {
            $this->renewFriends($uid);
            $this->renewFriends($fUid);
        }

        return $result;
    }

    /**
     * 删除好友关系
     */
    public function delFriend($uid, $fUid)
    {
        $result = Bll::friendCache()->delFriend($uid, $fUid);
        // 修改redis
        if ($result > 0) {
            $this->renewFriends($uid);
            $this->renewFriends($fUid);
        }

        return $result;
    }

    /**
     * 为保证数据同步，刷新 redis 数据
     */
    private function renewFriends($uid)
    {
        $friends = Model::friends()->getAll($uid);
        $friends = array_column($friends, 'fuid');

        // Redis数据
        $key = Keys::friends($uid);
        $cachedFriends = Dao::redis()->sMembers($key);

        // 删除已解除的好友
        $friendsDel = array_diff($cachedFriends, $friends);
        if ($friendsDel) {
            Dao::redis()->sRem($key, ...$friendsDel);
        }
        // 存入新好友
        $friendsAdd = array_diff($friends, $cachedFriends);
        if ($friendsAdd) {
            Dao::redis()->sAddArray($key, $friendsAdd);
        }
        //设置对应过期时间(3天过期);
        if (Dao::redis()->ttl($key) < 3600) {
            Dao::redis()->expire($key, 86400);
        }
        return $friends;
    }

    /**
     * 格式化用户信息
     */
    private function formatUserInfo($uids)
    {
        if (empty($uids)) return array();
        return Bll::user()->getUserInfoList($uids, 'uid,name,level');
    }

    /**
     * 通过 Uid 获取好友申请列表
     * @param $uid
     */
    public function getRequestFriends($uid)
    {
        $key = Keys::requestFriends($uid);
        $requestFriends = Dao::redis()->sMembers($key);

        // 检查数据
        if (empty($requestFriends)) {
            $requestFriends = $this->renewRequestFriends($uid);
        }

        return $requestFriends;
    }

    /**
     * 为保证数据同步，刷新 redis 数据
     */
    private function renewRequestFriends($uid)
    {
        $requestFriends = Model::friendsRequests()->getAll($uid);
        $requestFriends = array_column($requestFriends, 'uid');

        // Redis数据
        $key = Keys::requestFriends($uid);
        $cachedRequestFriends = Dao::redis()->sMembers($key);

        // 删除已解除申请的好友
        $requestFriendsDel = array_diff($cachedRequestFriends, $requestFriends);
        if ($requestFriendsDel) {
            Dao::redis()->sRem($key, ...$requestFriendsDel);
        }

        // 存入新申请好友
        $requsetFriendsAdd = array_diff($requestFriends, $cachedRequestFriends);
        if ($requsetFriendsAdd) {
            Dao::redis()->sAddArray($key, $requsetFriendsAdd);
        }

        return $requestFriends;
    }

    /**
     * 通过 Uid 获取用户的好友申请列表，包含申请好友的详情信息
     */
    public function getFriendsRequestInfo($uid)
    {

        $uids = $this->getRequestFriends($uid);
        return $this->formatUserInfo($uids);
    }

    /**
     * 添加好友申请关系
     */
    public function addFriendRequest($uid, $fUid, $requestScene = self::REQUEST_SCENE_DEFAULT)
    {
        $uuid = ChatLogBll::makeUUID($uid, $fUid);
        $friendReqInfo = Model::friendsRequests()->getOneById($uuid);
        if ($friendReqInfo && $friendReqInfo['status'] == 0) {
            if ($friendReqInfo['uid'] == $uid) {
                return true;
            }
            FF::throwException(Exceptions::RET_REQUEST_ADD_FRIEND_EXISTS);
        }
        // 更新结果
        $result = Model::friendsRequests()->addFriendRequest($uuid, $uid, $fUid);

        // 更新redis
        if ($result > 0) {
            $this->renewRequestFriends($fUid);
        }

        return $result;
    }

    /**
     * 拒绝好友申请
     */
    public function refuseFriendRequest($uid, $refuseUId)
    {
        // 更新结果
        $result = Model::friendsRequests()->refuseFriendRequest($uid, $refuseUId);

        // 更新redis
        if ($result > 0) {
            $this->renewRequestFriends($uid);
        }

        return $result;
    }

    /**
     * 赠送好友免费金币
     */
    public function checkSendFriendCoins($uid, $fUId)
    {
        $sendKey = keys::sentFriendCoins($uid, 'send');
        $coinsSentTimes = (int)Dao::redis()->hGet($sendKey, $fUId);
        // 检查今日是否能够发送免费金币
        if ($coinsSentTimes) {
            return false;
        }

        // 记录发送免费金币次数
        $incrTimes1 = Dao::redis()->hIncrBy($sendKey, $fUId, 1);
        if ($incrTimes1 == 1) {
            Dao::redis()->expire($sendKey, strtotime('tomorrow') - time());
        }

        return true;
    }

    public function checkSendFriendStamp($uid, $fUId)
    {
        $sendKey = keys::sentFriendStamp($uid, 'send');
        if (Dao::redis()->hGet($sendKey, $fUId)) {
            return false;
        }

        // 记录发送免费金币次数
        $incrTimes1 = Dao::redis()->hIncrBy($sendKey, $fUId, 1);
        if ($incrTimes1 == 1) {
            Dao::redis()->expire($sendKey, strtotime('tomorrow') - time());
        }

        return true;
    }

    /**
     * 获取发送申请次数（包括精确搜索和推荐）
     */
    public function getRequestsSentTimes($uid, &$requestsSentTimesLimit = 0)
    {
        // 发送好友申请次数限制
        $requestsSentTimesLimit = 100;
        $cacheKey = keys::requestFriendTimes($uid);
        $data = Dao::redis()->hGetAll($cacheKey);
        $searchTimes = (int)$data['scene_' . self::REQUEST_SCENE_SEARCH] ?? 0;
        $suggestTimes = (int)$data['scene_' . self::REQUEST_SCENE_SUGGEST] ?? 0;
        // 发送好友申请次数
        $requestsSentTimes = $searchTimes + $suggestTimes;
        $requestsSentTimes = min($requestsSentTimes, $requestsSentTimesLimit);

        return $requestsSentTimes;
    }

    public function bindInviter($uid, $inviteCode)
    {
        try {
            Dao::db()->transaction();
            $userInviteData = Model::userInviteData()->getOneById($uid);
            if (empty($userInviteData)) {
                FF::throwException(Exceptions::RET_ACCOUNT_NOT_EXIST);
            }

            if ($userInviteData['invitedBy']) {
                FF::throwException(Exceptions::RET_HAS_BIND_INVITER_ERROR, 'inviter has already been bound');
            }
            $inviterData = Model::userInviteData()->getOne(['code' => $inviteCode]);
            if (!$inviterData) {
                FF::throwException(Exceptions::RET_INVITE_CODE_NOT_EXISTS_ERROR, 'invite code not exists');
            }
            if ($inviterData['uid'] == $uid) {
                FF::throwException(Exceptions::RET_DENY_BIND_MYSELF_CODE_ERROR);
            }
            $where = ['uid' => $uid, 'updateTime' => $userInviteData['updateTime']];
            $result = Model::userInviteData()->update(['invitedBy' => $inviterData['uid']], $where);
            if (!$result) {
                FF::throwException(Exceptions::RET_BIND_INVITER_FAIL, 'bind invite code fail');
            }
            $updateRet = Model::userInviteData()->updateInviteUid($inviterData['uid'], $uid, $inviterData['updateTime']);
            if (!$updateRet) {
                FF::throwException(Exceptions::RET_BIND_INVITER_FAIL, 'bind invite code fail');
            }
            Dao::db()->commit();
            Bll::messageNotify()->invited($inviterData['uid'], $uid);
        } catch (Exception $e) {
            Dao::db()->rollback();
            FF::throwException($e->getCode(), $e->getMessage());
        }
    }

    public function awardFriendCoins($uid, $ids)
    {
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $where = ['uid' => $uid, 'id' => ['in', $ids], 'messageId' => MessageIds::RECEIVE_FRIEND_COINS_NOTIFY, 'status' => 0];
        $data = Model::userBllRewardData()->fetchAll($where);

        if (!$data) {
            FF::throwException(Exceptions::RET_REWARD_CLAIMED_ERROR, 'coins has already been claimed');
        }
        $coins = 0;
        $upIds = [];
        $fuids = [];
        foreach ($data as $row) {
            if ($row['expireTime'] && $row['expireTime'] < time()) {
                continue;
            }
            if (!$row['itemList']) {
                continue;
            }
            $itemList = json_decode($row['itemList'], true);
            $coins += array_sum(array_column($itemList, 'itemNum'));
            $upIds[] = $row['id'];
            $fuids[] = $row['triggerUid'];
        }

        if (!$upIds) {
            FF::throwException(Exceptions::RET_REWARD_EXPIRED_ERROR, 'coins reward has expired');
        }
        Model::userBllRewardData()->updateMulti($upIds, ['status' => 1]);
        Bll::friendCache()->batchUpdateFieldByInc($uid, $fuids, 'receiveGiftTimes');

        return $coins;
    }

    public function awardFriendStamp($uid, $ids)
    {
        try {
            if (!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            $where = ['uid' => $uid, 'id' => ['in', $ids], 'messageId' => MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY, 'status' => 0];
            $data = Model::userBllRewardData()->fetchAll($where);

            if (!$data) {
                FF::throwException(Exceptions::RET_REWARD_CLAIMED_ERROR, 'stamp has already been claimed');
            }
            $reward = [];
            $upIds = [];
            $fuids = [];
            foreach ($data as $row) {
                if ($row['expireTime'] && $row['expireTime'] < time()) {
                    continue;
                }
                if (!$row['itemList']) {
                    continue;
                }
                $itemList = json_decode($row['itemList'], true);
                foreach ($itemList as $item) {
                    $reward[$item['itemId']] += $item['itemNum'];
                }
                $fuids[] = $row['triggerUid'];
                $upIds[] = $row['id'];
            }
            if (!$upIds) {
                FF::throwException(Exceptions::RET_REWARD_EXPIRED_ERROR, 'stamp reward has expired');
            }
            Model::userBllRewardData()->updateMulti($upIds, ['status' => 1]);
            Bll::friendCache()->batchUpdateFieldByInc($uid, $fuids, 'receiveGiftTimes');
            $prizes = [];
            foreach ($reward as $itemId => $num) {
                $prizes[] = ['itemId' => $itemId, 'itemNum' => $num];
            }
            return $prizes;
        } catch (Exception $e) {
            FF::throwException($e->getCode(), $e->getMessage());
        }
    }

    public function getReceiveFriendGiftList($uid, $messageId)
    {
        $where = [
            'uid' => $uid, 'expireTime' => ['>=', time()],
            'messageId' => $messageId, 'status' => 0
        ];
        $data = Model::userBllRewardData()->fetchAll($where);

        $uids = array_column($data, 'triggerUid');
        $userMap = Bll::user()->getMulti($uids, ['name','level']);

        $list = [];
        foreach ($data as $row) {
            $list[] = [
                'id' => $row['id'],
                'sender' => $row['triggerUid'],
                'senderName' => $userMap[$row['triggerUid']] ? $userMap[$row['triggerUid']]['name'] : $row['triggerUid'],
                'senderLevel' => $userMap[$row['triggerUid']] ? $userMap[$row['triggerUid']]['level'] : 0,
                'sendTime' => $row['time'],
                'expireTime' => $row['expireTime'],
                'itemList' => json_decode($row['itemList'], true),
            ];
        }
        return $list;
    }

    public function getSuggestFriends($uid)
    {
        //存在好友 则不能推荐
        if (Bll::friends()->getFriends($uid)) {
            return [];
        }
        //从集合中获取
        return [];
    }

    public function getReceiveFriendGiftCount($uid, $messageId)
    {
        $where = [
            'uid' => $uid, 'expireTime' => ['>=', time()],
            'messageId' => $messageId, 'status' => 0
        ];

        return Model::userBllRewardData()->getCount($where);
    }

    public function coinToItemList($coins)
    {
        return [['itemId' => 'coins', 'itemNum' => $coins]];
    }

    public function getUnreadCount($uid)
    {
        return Model::friends()->getUnreadCnt($uid);
    }
}