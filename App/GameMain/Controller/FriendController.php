<?php
/**
 * 社交好友相关接口
 */

namespace FF\App\GameMain\Controller;

use FF\Bll\FriendsBll;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Constants\Exceptions;
use Exception;

class FriendController extends BaseController
{
    /**
     * 获取好友列表
     * 步骤：获取好友列表，配置信息齐全，发送结果
     */
    public function fetchFriends()
    {
        $uid = $this->getUid();
        $friends = Bll::friends()->getFriendsInfo($uid);
        return array(
            'friends' => array_values($friends),
        );
    }

    /**
     * 添加好友关系
     * 步骤：获取待添加用户ID，检查用户是否存在，检查当前用户好友是否满100，是否已是好友；否，发送好友申请邮件，发送成功通知；
     */
    public function addFriend()
    {
        $uid = $this->getUid();
        $searchUid = (int)$this->getParam('searchUid');
        $requestScene = (int)$this->getParam('requestScene', false, 0);

        // 防止添加自己
        if ($uid == $searchUid) {
            FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_ACCEPT_FAILED);
        }

        // 检查用户角色是否存在
        $searchRole = Bll::user()->getUserInfo($searchUid, 'uid');
        if (empty($searchRole['uid'])) {
            FF::throwException(Exceptions::RET_ACCOUNT_NOT_EXIST);
        }

        // 获取用户好友列表，检查当前用户好友是否满100
        $friends = Bll::friends()->getFriends($uid);
        if (count($friends) >= 100) {
            FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_IS_FULL);
        }

        // 检查是否已经是好友
        if (in_array($searchUid, $friends)) {
            FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_ADDED);
        }

        // 发送申请, 并通知接收用户ID
        $addRet = Bll::friends()->addFriendRequest($uid, $searchUid, $requestScene);
        if ($addRet) {
            //好友申请通知
            Bll::messageNotify()->AddFriendRequest($searchUid, $uid);
        }
        // 成功发送
        return array();
    }

    /**
     * 获取好友申请列表
     */
    public function fetchFriendsRequests()
    {
        $uid = $this->getUid();
        $requestFriends = Bll::friends()->getFriendsRequestInfo($uid);

        return array(
            'requestFriends' => array_values($requestFriends)
        );
    }

    /**
     * 接受好友关系
     * 步骤：获取请求用户ID，检查当前用户是否满100，与请求用户是否满100；否，建立好友关系。
     */
    public function acceptFriend()
    {
        // 参数
        $uid = $this->getUid();
        $reqUid = (int)$this->getParam('reqUid');

        // 获取用户好友列表，检查当前用户好友是否满100
        $myFriends = Bll::friends()->getFriends($uid);
        if (count($myFriends) >= 100) FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_IS_FULL, 'The number of friends has reached its limit.');

        // 获取对方用户好友列表，检查对方用户好友是否满100
        $reqFriends = Bll::friends()->getFriends($reqUid);
        if (count($reqFriends) >= 100) FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_IS_FULL, 'The number of friends has reached its limit.');

        // 满足条件，建立好友关系
        $result = Bll::friends()->addFriend($uid, $reqUid);

        // 建立失败
        if (!$result) FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_ACCEPT_FAILED, 'Fail to accept');

        Bll::messageNotify()->accessFriend($reqUid, $uid);

        return array(
            'friends' => Bll::friends()->getFriendsInfo($uid)
        );
    }

    /**
     * 拒绝好友关系
     */
    public function refuseFriend()
    {
        // 参数
        $uid = $this->getUid();
        $refuseUid = (int)$this->getParam('refuseUid');

        $result = Bll::friends()->refuseFriendRequest($uid, $refuseUid);
        if (!$result) FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_ACCEPT_FAILED, 'Fail to refuse');

        Bll::messageNotify()->refuseFriend($refuseUid, $uid);
        // 拒绝成功
        return array();
    }

    /**
     * 删除好友关系
     * 步骤：删除双方好友关系
     */
    public function delFriend()
    {
        // 参数
        $uid = $this->getUid();
        $delUid = (int)$this->getParam('delUid');

        // 直接删除好友关系
        $result = Bll::friends()->delFriend($uid, $delUid);

        // 删除失败
        if (!$result) FF::throwException(Exceptions::RET_SOCIAL_FRIENDS_DELETE_FAILED, 'Fail to delete');

        Bll::messageNotify()->delFriend($delUid, $uid);

        return array(
            'friends' => Bll::friends()->getFriendsInfo($uid)
        );
    }

    /**
     * 获取建议好友列表
     */
    public function fetchSuggestFriends()
    {
        $uid = $this->getUid();
        $suggestFriends = Bll::friends()->getSuggestFriends($uid);

        return ['list' => array_values($suggestFriends)];
    }

    /**
     * 添加建议好友列表（批量）
     */
    public function addSuggestFriends()
    {
        $uid = $this->getUid();
        $suggestUids = $this->getParam('suggestUids', false, []);
        if (!is_array($suggestUids)) {
            $suggestUids = json_decode($suggestUids, true);
        }

        if (!is_array($suggestUids)) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }
        $friends = Bll::friends()->getFriends($uid);
        // 遍历推荐好友列表
        foreach ($suggestUids as $suggestUid) {
            if ($suggestUid == $uid) {
                continue;
            }
            // 检查用户角色是否存在
            $searchRole = Bll::user()->getUserInfo($suggestUid, 'uid');
            if (empty($searchRole['uid'])) {
                continue;
            }

            // 获取用户好友列表，检查当前用户好友是否满100
            if (count($friends) >= 100) {
                break;
            }

            // 检查是否已经是好友
            if (in_array($suggestUid, $friends)) {
                continue;
            }
            // 发送申请, 并通知接收用户ID
            Bll::friends()->addFriendRequest($uid, $suggestUid, FriendsBll::REQUEST_SCENE_SUGGEST);
            Bll::messageNotify()->addFriendRequest($suggestUid, $uid);
        }
        // 成功发送
        return array();
    }

    public function fetchReceiveFriendStampList()
    {
        $uid = $this->getUid();
        $list = Bll::friends()->getReceiveFriendGiftList($uid, MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY);
        return ['list' => $list];
    }

    public function fetchReceiveFriendCoinList()
    {
        $uid = $this->getUid();
        $list = Bll::friends()->getReceiveFriendGiftList($uid, MessageIds::RECEIVE_FRIEND_COINS_NOTIFY);
        return ['list' => $list];
    }

    public function givingFriendStamp()
    {
        // 参数
        $uid = $this->getUid();
        $fUid = (int)$this->getParam('fUid');
        $itemList = $this->getParam('itemList');
        if (!is_array($itemList)) {
            $itemList = json_decode($itemList, true);
        }
        if (!is_array($itemList)) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }

        foreach ($itemList as $item) {
            if (!is_array($item)) {
                FF::throwException(Exceptions::PARAM_INVALID_ERROR);
            }
            if (empty($item['itemId']) || empty($item['itemNum'])) {
                FF::throwException(Exceptions::PARAM_MISS_ERROR);
            }
        }
        // 检查是否好友
        if (!Bll::friends()->isMyFriend($uid, $fUid)) {
            FF::throwException(Exceptions::RET_SOCIAL_NOT_FRIEND);
        }

        $key = Keys::sentFriendStampLock($uid, $fUid);
        if (!Dao::redis()->set($key, 1, ['nx', 'ex' => 1])) {
            FF::throwException(Exceptions::RET_REPEAT_REQUEST_ERROR, 'please try again later.');
        }
        $result = Bll::friends()->checkSendFriendStamp($uid, $fUid);
        Dao::redis()->del($key);
        if (!$result) {
            FF::throwException(Exceptions::RET_SOCIAL_LIMIT_SENT_FRIEND_STAMP);
        }

        Model::userBllRewardData()->record($fUid, $uid, MessageIds::RECEIVE_FRIEND_STAMP_NOTIFY, $itemList, 30 * 86400);
        Bll::friendCache()->batchUpdateFieldByInc($uid, [$fUid], 'givingGiftTimes');
        Bll::messageNotify()->receiveFriendStamp($fUid, $uid);
        return [];
    }

    public function awardFriendCoins()
    {
        $uid = $this->getUid();
        $ids = $this->getParam('ids');
        $key = Keys::awardFriendCoinsLock($uid);
        if (!Dao::redis()->set($key, 1, ['nx', 'ex' => 1])) {
            FF::throwException(Exceptions::RET_REPEAT_REQUEST_ERROR, 'please try again later.');
        }
        try {
            $coin = Bll::friends()->awardFriendCoins($uid, $ids);
            Dao::redis()->del($key);
            return array('coin' => (int)$coin);
        } catch (Exception $e) {
            Dao::redis()->del($key);
            FF::throwException($e->getCode(), $e->getMessage());
        }
    }

    public function awardFriendStamp()
    {
        $uid = $this->getUid();
        $ids = $this->getParam('ids');
        $key = Keys::awardFriendStampLock($uid);
        if (!Dao::redis()->set($key, 1, ['nx', 'ex' => 1])) {
            FF::throwException(Exceptions::RET_REPEAT_REQUEST_ERROR, 'please try again later.');
        }
        try {
            $reward = Bll::friends()->awardFriendStamp($uid, $ids);
            Dao::redis()->del($key);
            return ['prizes' => $reward];
        } catch (Exception $e) {
            Dao::redis()->del($key);
            FF::throwException($e->getCode(), $e->getMessage());
        }
    }

    //绑定邀请者
    public function bindInviter()
    {
        $uid = $this->getUid();
        $inviteCode = $this->getParam('inviteCode', false, '');
        $inviter = $this->getParam('inviter', false, '');
        if (!$inviteCode && !$inviter) {
            FF::throwException(Exceptions::PARAM_MISS_ERROR, 'params miss');
        }

        Bll::friends()->bindInviter($uid, $inviter, $inviteCode);
        return [];
    }

    /**
     * 赠送好友免费金币
     */
    public function givingFriendsCoins()
    {
        // 参数
        $uid = $this->getUid();
        $list = $this->getParam('list');
        if (!is_array($list)) {
            $list = json_decode($list, true);
        }
        if (!is_array($list)) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }
        $friends = Bll::friends()->getFriends($uid);
        foreach ($list as $info) {
            if (empty($info['fUid']) || empty($info['coin'])) {
                FF::throwException(Exceptions::PARAM_MISS_ERROR);
            }
            if ($info['coin'] <= 0) {
                FF::throwException(Exceptions::RET_GIVING_COIN_ERROR, 'the giving coin num incorrect');
            }
            if (!in_array($info['fUid'], $friends)) {
                FF::throwException(Exceptions::RET_SOCIAL_NOT_FRIEND);
            }
        }
        $successUids = [];
        foreach ($list as $info) {
            $fUid = $info['fUid'];
            $coin = min($info['coin'], 100000000);
            $key = Keys::sendFriendCoinsLock($uid, $fUid);
            if (!Dao::redis()->set($key, 1, ['nx', 'ex' => 1])) {
                continue;
            }
            $result = Bll::friends()->checkSendFriendCoins($uid, $fUid);
            Dao::redis()->del($key);
            if (!$result) {
                continue;
            }
            $itemList = Bll::friends()->coinToItemList($coin);
            Model::userBllRewardData()->record($fUid, $uid, MessageIds::RECEIVE_FRIEND_COINS_NOTIFY, $itemList, 30 * 86400);
            Bll::friendCache()->batchUpdateFieldByInc($uid, [$fUid], 'givingGiftTimes');
            Bll::messageNotify()->receiveFriendCoins($fUid, $uid);
            $successUids[] = $fUid;
        }
        return ['fUids' => $successUids];
    }

    //一键添加俱乐部为好友
    public function oneClickAddClubFriend()
    {
        $uid = $this->getUid();
        $friends = Bll::friends()->getFriends($uid);
        $clubId = Bll::club()->getClubIdByUid($uid);
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $memberUids = Bll::club()->getClubMembers($clubId);
        // 遍历推荐好友列表
        foreach ($memberUids as $memberUid) {

            if ($memberUid == $uid) {
                continue;
            }
            // 获取用户好友列表，检查当前用户好友是否满100
            if (count($friends) >= 100) {
                break;
            }

            // 检查是否已经是好友
            if (in_array($memberUid, $friends)) {
                continue;
            }
            // 发送申请, 并通知接收用户ID
            Bll::friends()->addFriendRequest($uid, $memberUid, FriendsBll::REQUEST_SCENE_SUGGEST);
            Bll::messageNotify()->addFriendRequest($memberUid, $uid);
        }
        // 成功发送
        return array();
    }

}