<?php

namespace FF\Bll;

use Exception;
use FF\Constants\Exceptions;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class ClubBll
{
    const ROLE_LEADER = 1;
    const ROLE_CO_LEADER = 2;
    const ROLE_DONATE_MVP = 3;
    const ROLE_POINTS_MVP = 4;
    const ROLE_MEMBER = 5;

    const TYPE_PRIVATE = 1;
    const TYPE_PUBLIC = 2;
    //禁言
    const MUTE_STATUS_ACTIVE = 1;
    const MUTE_STATUS_INACTIVE = 0;

    const REQUEST_STATUS_INVITE = 1;
    const REQUEST_STATUS_ACCEPT = 2;
    const REQUEST_STATUS_REFUSE = 3;
    const  CHAT_TYPE_PUBLISH_HELP_COIN = 1;
    const  CHAT_TYPE_PUBLISH_HELP_STAMP = 2;

    private static $publishHelpType = [
        self::CHAT_TYPE_PUBLISH_HELP_STAMP,
        self::CHAT_TYPE_PUBLISH_HELP_COIN,
    ];
    public static $clubRoleMapName = [
        self::ROLE_LEADER => 'LEADER',
        self::ROLE_CO_LEADER => 'CO LEADER',
        self::ROLE_DONATE_MVP => 'DONATE MVP',
        self::ROLE_POINTS_MVP => 'POINTS MVP',
        self::ROLE_MEMBER => '',
    ];

    const CLUB_REWARD_TYPE_JACKPOT = 1; //jackpot
    const CLUB_REWARD_TYPE_BOX_RANK = 2; //ClubChest
    const CLUB_REWARD_TYPE_PIECE_NODE = 3; //俱乐部活动奖励
    const CLUB_REWARD_TYPE_RANK = 4; //Club League
    const CLUB_REWARD_TYPE_PUBLISH_HELP_COINS = 5; //发布帮助金币奖励
    const CLUB_REWARD_TYPE_PUBLISH_HELP_STAMP = 6;//发布帮助邮票奖励


    protected static $clubChatHelpRewardTypeMap = [
        self::CHAT_TYPE_PUBLISH_HELP_COIN => self::CLUB_REWARD_TYPE_PUBLISH_HELP_COINS,
        self::CHAT_TYPE_PUBLISH_HELP_STAMP => self::CLUB_REWARD_TYPE_PUBLISH_HELP_STAMP,
    ];

    //获取俱乐部列表
    public function getSuggestList($count)
    {
        $key = Keys::suggestClubSet();
        $clubIds = Dao::redis()->sRandMember($key, $count);
        return Bll::clubCache()->getClubList($clubIds, 'type,vipLimit,clubName,level,headId,memberCnt,points,dan');
    }

    //查询俱乐部
    public function searchClubList($keyword)
    {
        $clubList = Model::clubs()->fetchAll(['clubName' => ['like', "{$keyword}%"]]);

        if (!$clubList) {
            return [];
        }
        foreach ($clubList as &$clubInfo) {
            unset($clubInfo['coins'], $clubInfo['creator']);
        }

        return $clubList;
    }

    //创建俱乐部
    public function createClub($uid, $params)
    {
        $this->checkClubParams($params);
        $info = Model::clubUsers()->getOneById($uid);
        if ($info) {
            FF::throwException(Exceptions::FAILED);
        }
        Dao::db()->transaction();
        try {
            $insert = [
                'clubName' => $params['clubName'],
                'headId' => $params['headId'],
                'type' => $params['type'],
                'vipLimit' => $params['vipLimit'],
                'creator' => $uid,
            ];
            $clubId = Model::clubs()->insert($insert);
            if (!$clubId) {
                FF::throwException(Exceptions::RET_CLUB_CREATE_ERROR);
            }
            $flag = Model::clubUsers()->insert(['clubId' => $clubId, 'uid' => $uid, 'role' => self::ROLE_LEADER]);

            if (!$flag) {
                FF::throwException(Exceptions::RET_CLUB_CREATE_ERROR);
            }
            Dao::db()->commit();
            return $clubId;
        } catch (Exception $e) {
            Dao::db()->rollback();
            FF::throwException(Exceptions::RET_CLUB_CREATE_ERROR);
        }
    }

    //申请加入俱乐部
    public function joinClub($uid, $clubId, $invitedBy = 0)
    {
        $clubInfo = $this->getInfo($clubId);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        if ($clubInfo['type'] == self::TYPE_PRIVATE && !$invitedBy) {
            FF::throwException(Exceptions::RET_CLUB_NOT_ALLOW_JOIN_ERROR);
        }

        $userInfo = Bll::user()->getUserInfo($uid);
        if ($userInfo['vipLevel'] < $clubInfo['vipLimit']) {
            FF::throwException(Exceptions::RET_VIP_LEVEL_NOT_ENOUGH_ERROR);
        }

        if (Model::clubUsers()->getOneById($uid)) {
            FF::throwException(Exceptions::RET_USER_ALREADY_IN_CLUB_ERROR);
        }

        $flag = Model::clubUsers()->insert(['clubId' => $clubId, 'uid' => $uid]);
        if (!$flag) {
            FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
        }

        Model::clubs()->update(['memberCnt' => ['+=', 1]], ['clubId' => $clubId]);
    }

    //拒绝加入俱乐部
    public function refuseInviteJoinClub($uid, $clubId, $invitedBy)
    {
        $where = ['uid' => $uid, 'clubId' => $clubId, 'invitedBy' => $invitedBy, 'status' => self::REQUEST_STATUS_INVITE];
        $requestInfo = Model::userClubRequestLog()->fetchOne($where);
        if (!$requestInfo) {
            return;
        }
        Model::userClubRequestLog()->update(['status' => self::REQUEST_STATUS_REFUSE], $where);
        Bll::messageNotify()->pushNotifyMsg($invitedBy, $uid, MessageIds::CLUB_INVITE_JOIN_REFUSE_NOTIFY);
    }

    //获取俱乐部信息
    public function getInfo($clubId)
    {
        $info = Bll::clubCache()->getCacheData($clubId);
        $info['rank'] = $this->getClubRank($clubId);
        return $info;
    }

    //退出俱乐部
    public function quitClub($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }

        if ($info['role'] == self::ROLE_LEADER) {
            FF::throwException(Exceptions::RET_CLUB_LEADER_NOT_QUIT_ERROR);
        }

        if (!Model::clubUsers()->delete(['uid' => $uid])) {
            FF::throwException(Exceptions::FAILED);
        }
        Model::clubs()->update(['memberCnt' => ['-=', 1]], ['clubId' => $info['clubId']]);
    }

    //邀请进入俱乐部
    public function inviteJoinClub($uid, $tuid)
    {
        if (Model::clubUsers()->getOneById($tuid)) {
            FF::throwException(Exceptions::RET_USER_ALREADY_IN_CLUB_ERROR);
        }
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }

        Model::userClubRequestLog()->addRequest($tuid, $info['clubId'], $uid);
        Bll::messageNotify()->pushNotifyMsg($tuid, $uid, MessageIds::CLUB_INVITE_JOIN_NOTIFY);
    }

    public function acceptInviteJoinClub($uid, $clubId, $invitedBy)
    {
        $where = ['uid' => $uid, 'clubId' => $clubId, 'invitedBy' => $invitedBy];
        $requestInfo = Model::userClubRequestLog()->fetchOne($where);
        if (!$requestInfo || $requestInfo['status'] != self::REQUEST_STATUS_INVITE) {
            FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
        }
        $this->joinClub($uid, $clubId, $invitedBy);
        Model::userClubRequestLog()->update(['status' => self::REQUEST_STATUS_ACCEPT], $where);
        Bll::messageNotify()->pushNotifyMsg($invitedBy, $uid, MessageIds::CLUB_INVITE_JOIN_SUCCESS_NOTIFY);
    }

    //获取俱乐部成员列表
    public function getMemberList($uid, $clubId, $page, $pageSize)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info && !$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }

        if (!$clubId) {
            $clubId = $info['clubId'];
        }
        $clubInfo = $this->getInfo($clubId);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        if ($clubInfo['type'] == self::TYPE_PRIVATE && $info['clubId'] != $clubId) {
            return [];
        }
        $pageSize = max(min($pageSize, 200), 10);
        $offset = ($page > 0 ? ($page - 1) : 0) * $pageSize;

        $fields = 'uid,role,points';
        if ($clubId == $info['clubId']) {
            $fields = 'uid,role,points,coins';
        }
        $memberList = Model::clubUsers()->fetchAll(['clubId' => $clubId], $fields, ['createTime' => 'ASC'], '', $pageSize, $offset);
        if (!$memberList) {
            return [];
        }
        $uids = array_column($memberList, 'uid');
        $userList = Bll::user()->getUserInfoList($uids, ['name', 'level', 'headId', 'headFrameId', 'lastOnlineTime']);
        foreach ($memberList as &$member) {
            if (empty($userList[$member['uid']])) {
                continue;
            }
            $member['roleName'] = self::$clubRoleMapName[$member['role']] ?? '';
            $member = array_merge($member, $userList[$member['uid']]);
            $member['isOnline'] = Bll::user()->isOnlineByLoginTime($userList[$member['uid']]['lastOnlineTime']);
        }
        return $memberList;
    }

    //踢出俱乐部
    public function kickOutClubMember($uid, $tuid)
    {
        if ($uid == $tuid) {
            FF::throwException(Exceptions::FAILED);
        }

        $info = Model::clubUsers()->getOneById($uid);
        if (!$info || $info['role'] != self::ROLE_LEADER) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }
        $memberInfo = Model::clubUsers()->getOneById($tuid);
        if (!$memberInfo || $memberInfo['clubId'] != $info['clubId']) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }
        if (!Model::clubUsers()->delete(['uid' => $tuid])) {
            FF::throwException(Exceptions::FAILED);
        }
        Model::clubs()->update(['memberCnt' => ['-=', 1]], ['clubId' => $info['clubId']]);

        Bll::messageNotify()->kickOutClub($tuid, $uid);
    }

    //解散俱乐部
    public function dissolveClub($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info || $info['role'] != self::ROLE_LEADER) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }
        Model::clubUsers()->delete(['clubId' => $info['clubId']], 0);
        Model::clubs()->delete(['clubId' => $info['clubId']]);
    }

    public function setMuteStatus($uid, $tuid)
    {
        if ($uid == $tuid) {
            FF::throwException(Exceptions::FAILED);
        }

        $info = Model::clubUsers()->getOneById($uid);
        if (!$info || $info['role'] != self::ROLE_LEADER) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }
        $memberInfo = Model::clubUsers()->getOneById($tuid);

        if (!$memberInfo || $memberInfo['clubId'] != $info['clubId']) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }
        Model::clubUsers()->update(['muteStatus' => (int)(!$memberInfo['muteStatus'])], ['uid' => $tuid]);
        Bll::messageNotify()->clubMute($tuid, $uid, !$memberInfo['muteStatus']);
    }

    public function chat($uid, $type, $content)
    {
        Bll::chatLog()->checkChatContent($content);
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }

        if ($info['muteStatus'] == self::MUTE_STATUS_ACTIVE) {
            FF::throwException(Exceptions::RET_CHAT_FORBIDDEN_ERROR);
        }
        $expireTime = 0;
        if (in_array($type, [self::CHAT_TYPE_PUBLISH_HELP_COIN, self::CHAT_TYPE_PUBLISH_HELP_STAMP])) {
            $expireTime = $this->publishHelp($uid, $type);
        }

        $helpLimit = Config::get('club', 'helpLimit/' . $type, false);
        $insert = [
            'clubId' => $info['clubId'],
            'content' => $content,
            'sender' => $uid,
            'type' => $type,
            'time' => time(),
            'expireTime' => $expireTime,
            'helpLimit' => $helpLimit ?: 0,
            'microtime' => floor(_microtime()),
        ];

        if (!Model::clubChatLog()->insert($insert)) {
            FF::throwException(Exceptions::FAILED);
        }
        $key = Keys::clubChatInfo($info['clubId']);
        Dao::redis()->hMSet($key, ['chatTime' => $insert['time'], 'sender' => $uid]);
        Dao::redis()->expire($key, 120);
    }

    public function updateClubInfo($uid, $params)
    {
        $this->checkClubParams($params);
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info || $info['role'] != self::ROLE_LEADER) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }

        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $update = [
            'clubName' => $params['clubName'],
            'headId' => $params['headId'],
            'type' => $params['type'],
            'vipLimit' => $params['vipLimit'],
        ];

        if (!Model::clubs()->update($update, ['clubId' => $info['clubId']])) {
            FF::throwException(Exceptions::RET_CLUB_UPDATE_ERROR);
        }

        return $info['clubId'];
    }

    protected function checkClubParams(&$params)
    {
        if (!isset($params['clubName']) || !isset($params['headId']) || !isset($params['type'])) {
            FF::throwException(Exceptions::PARAM_MISS_ERROR);
        }

        if (strlen($params['clubName']) > 32) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }

        if (!in_array($params['type'], [self::TYPE_PRIVATE, self::TYPE_PUBLIC])) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }

        if (empty($params['vipLimit'])) {
            $params['vipLimit'] = 0;
        }

        $params['vipLimit'] = (int)$params['vipLimit'];
        $params['headId'] = (int)$params['headId'];
    }

    public function donateCoins($uid, $coins)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        if ($coins <= 0) {
            return true;
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $result = Bll::clubCache()->updateClubByInc($info['clubId'], 'coins', $coins);
        if (!$result) {
            FF::throwException(Exceptions::FAILED);
        }
        Model::clubUsers()->update(['coins' => ['+=', $coins]], ['uid' => $uid]);

        return $this->getInfo($info['clubId']);
    }

    public function pointsReport($uid, $points)
    {
        if ($points <= 0) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }
        $info = Bll::clubUser()->getInfo($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        Model::clubUsers()->update(['points' => ['+=', $points]], ['uid' => $uid]);
        Bll::rank()->setScore($info['clubId'], Bll::rank()->getClubType(), $points);

        Bll::rank()->setScore($uid, Bll::rank()->getClubBoxType($info['clubId']), $points);
        $key = Keys::clubboxPoints($info['clubId'], $this->getCurrBoxActDate());
        $seasonPoints = Dao::redis()->incrBy($key, $points);

        return [
            'seasonPoints' => (int)$seasonPoints,
            'totalPoints' => (int)$seasonPoints + 100,
        ];
    }

    public function dropPuzzle($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        $key = Keys::puzzle($info['clubId'], $this->getCurrBoxActDate());
        $pieceId = (int)Dao::redis()->lPop($key);
        if (!$pieceId) {
            FF::throwException(Exceptions::RET_CLUB_PUZZLE_FINISH_ERROR);
        }
        $key = Keys::clubPuzzle($info['clubId'], $this->getCurrBoxActDate());
        Dao::redis()->sAdd($key, $pieceId);

        return $pieceId;
    }

    public function getCurrBoxActDate()
    {
        if (!$w = date('w')) {
            return date('Ymd');
        }
        return date('Ymd', time() + (7 - $w) * 86400);
    }

    public function getClubIdByUid($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            return 0;
        }

        $clubInfo = $this->getInfo($info['clubId']);

        return $clubInfo ? $clubInfo['clubId'] : 0;
    }

    public function publishHelp($uid, $type)
    {
        if (!isset(self::$publishHelpType[$type])) {
            FF::throwException(Exceptions::RET_CLUB_PUBLISH_HELP_TYPE_ERROR);
        }

        $info = Bll::clubUser()->getInfo($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        $key = Keys::lastHelpTime($uid, $type);
        $coolTime = Config::get('club', 'publishHelp/coolTime');
        if (!Dao::redis()->set($key, time(), ['nx', 'ex' => $coolTime])) {
            FF::throwException(Exceptions::RET_CLUB_PUBLISH_HELP_COOL_DOWN);
        }
        $duration = Config::get('club', 'publishHelp/duration');
        return $duration + time();
    }

    public function jackpotReport($uid, $coins)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            return;
        }

        $bonusRate = Config::get('club', 'jackpotBonusRate');
        $data = [
            'clubId' => $clubId,
            'uid' => $uid,
            'coins' => $coins,
            'rewardCoins' => max(1, floor($coins * $bonusRate)),
        ];
        Model::clubJackpotLog()->insert($data);
    }

    public function machinePointsCollect($uid, $points)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $rankType = Bll::rank()->getClubMachinePointsType($info['clubId']);

        Bll::rank()->setScore($uid, $rankType, $points);
    }

    public function fetchMachinePointsRankList($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $rankType = Bll::rank()->getClubMachinePointsType($info['clubId']);
        $rankList = Bll::rank()->getList($rankType, 0, 20);
        $uids = array_keys($rankList);
        if (empty($uids)) {
            return [];
        }
        $userList = Bll::user()->getUserInfoList($uids, 'name,headId');
        $rank = 1;
        $list = [];
        foreach ($rankList as $_uid => $score) {
            if (empty($userList[$_uid])) continue;
            $list[] = [
                'points' => (int)$score,
                'rank' => $rank++,
                'name' => $userList[$_uid]['name'],
                'uid' => $_uid,
            ];
        }

        return ['rankList' => $list, 'myRank' => $this->getMyPointRank($uid, $info['clubId'])];
    }

    public function getMyClubRank($uid)
    {
        $myClubId = Bll::club()->getClubIdByUid($uid);
        return $myClubId ? Bll::rank()->getRank($myClubId, Bll::rank()->getClubType()) : 0;
    }

    public function getMyPointRank($uid, $clubId)
    {
        $rankType = Bll::rank()->getClubMachinePointsType($clubId);
        return Bll::rank()->getRank($uid, $rankType);
    }

    public function getClubRank($clubId)
    {
        return Bll::rank()->getRank($clubId, Bll::rank()->getClubType());
    }

    public function getClubMembers($clubId)
    {
        $key = Keys::clubMember($clubId);
        $members = Dao::redis()->sMembers($key);
        if ($members) {
            return $members;
        }
        $memberList = Model::clubUsers()->fetchAll(['clubId' => $clubId], 'uid');
        if (!$memberList) {
            return [];
        }
        $members = array_column($memberList, 'uid');
        Dao::redis()->sAdd($key, ...$members);

        return $members;
    }

    public function getClubRewardList($uid)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $time = time();
        $where = ['uid' => $uid, 'clubId' => $info['clubId'], 'expireTime' => ['>=', $time], 'status' => 0];
        $rewardList = Model::clubRewards()->fetchAll($where, null, 'createTime desc', [], 10);
        $list = [];
        foreach ($rewardList as $reward) {
            $row = [
                'set' => $reward['set'],
                'type' => $reward['type'],
                'progress' => $reward['progress'],
                'ttl' => $reward['expireTime'] - $time,
                'itemList' => $reward['itemList'] ? json_decode($reward['itemList'], true) : [],
                'extData' => $reward['extData'] ? json_decode($reward['extData'], true) : [],
            ];

            $uids = array_column($row['extData'], 'uid');
            $userList = Bll::user()->getUserInfoList($uids, 'headId, headFrameId');
            foreach ($row['extData'] as &$extInfo) {
                if (!isset($extInfo['uid']) || !isset($userList[$extInfo['uid']])) {
                    continue;
                }
                $extInfo['headId'] = $userList[$extInfo['uid']]['headId'];
                $extInfo['headFrameId'] = $userList[$extInfo['uid']]['headFrameId'];
            }

            $list[] = $row;
        }
        return $list;
    }

    //俱乐部领取奖励
    public function claimClubReward($uid, $sets)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $key = Keys::clubAwardLock($uid);
        if (!Dao::redis()->set($key, 1, ['nx', 'ex' => 1])) {
            FF::throwException(Exceptions::RET_REPEAT_REQUEST_ERROR, 'please try again later.');
        }

        $where = ['uid' => $uid, 'clubId' => $clubId, 'set' => ['in', $sets], 'status' => 0];
        $rewardList = Model::clubRewards()->fetchAll($where, 'itemList');
        $itemMap = [];
        foreach ($rewardList as $row) {
            $_itemList = json_decode($row['itemList'], true);
            foreach ($_itemList as $_item) {
                $itemMap[$_item['id']] += $_item['num'];
            }
        }
        Model::clubRewards()->update(['status' => 1], $where, 0);
        Dao::redis()->del($key);
        $list = [];
        foreach ($itemMap as $itemId => $num) {
            $list[] = [
                'id' => $itemId,
                'num' => $num,
            ];
        }
        return $list;
    }

    public function getClubRewardInfo($uid, $set)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $rewardInfo = Model::clubRewards()->fetchOne(['uid' => $uid, 'clubId' => $clubId, 'set' => $set]);
        if (!$rewardInfo || $rewardInfo['expireTime'] < time()) {
            FF::throwException(Exceptions::RET_SEASON_REWARD_EXPIRED_ERROR);
        }
        $userStatList = [];
        switch ($rewardInfo['type']) {
            case self::CLUB_REWARD_TYPE_JACKPOT:
                $userStatList = $this->getUserJackpotStat($rewardInfo['createTime']);
                break;
            case self::CLUB_REWARD_TYPE_PUBLISH_HELP_COINS:
            case self::CLUB_REWARD_TYPE_PUBLISH_HELP_STAMP:
                $userStatList = $this->getHelperList($rewardInfo);
                break;
            default:
                $userStatList = $this->getUserScoreList($set);
                break;
        }

        $userList = Bll::user()->getUserInfoList(array_column($userStatList, 'uid'), '');
        foreach ($userStatList as &$info) {
            $_uid = $info['uid'];
            if (empty($userList[$_uid])) {
                continue;
            }

            $info['name'] = $userList[$_uid]['name'];
            $info['headId'] = $userList[$_uid]['headId'];
            $info['headFrameId'] = $userList[$_uid]['headFrameId'];
        }

        return $userStatList;
    }

    public function getUserJackpotStat($settleTime)
    {
        $yesterday = strtotime('-1 day', strtotime($settleTime));
        $date = date('Y-m-d 00:00:00', $yesterday);
        $key = Keys::clubUserJackpotStat($date);
        $list = Dao::redis()->get($key);
        if ($list) {
            return json_decode($list, true);
        }

        $endData = date('Y-m-d 23:59:59', $yesterday);
        $where = ['hitTime' => ['between', [$date, $endData]]];
        $logData = Model::clubJackpotLog()->fetchAll($where, 'uid,sum(rewardCoins) coins, count(1) times ', 'times desc', ['uid'], 50);
        $list = [];
        foreach ($logData as $row) {
            $list[] = [
                'uid' => $row['uid'],
                'progress' => $row['times'],
                'itemList' => [['id' => 'coins', 'num' => $row['coins']]],
            ];
        }
        Dao::redis()->set($key, json_encode($list), 86400);

        return $list;
    }

    public function getHelperList($rewardInfo)
    {
        $helpers = explode(',', $rewardInfo['helpers']);
        $list = [];
        foreach ($helpers as $helper) {
            $list[] = [
                'uid' => $helper,
                'progress' => 1,
                'itemList' => [],
            ];
        }
        return $list;
    }

    public function getUserScoreList($set)
    {
        $key = Keys::userTopRank($set);
        $list = Dao::redis()->get($key);
        if ($list) {
            return $list;
        }
        $list = Model::clubRewards()->fetchAll(['set' => $set], 'uid,points as progress,itemList', 'points desc', [], 50);
        foreach ($list as &$row) {
            $row['itemList'] = $row['itemList'] ? json_decode($row['itemList'], true) : [];
        }
        Dao::redis()->set($key, json_encode($list), 86400);

        return $list;
    }

    public function chatHelp($uid, $chatId)
    {
        $chatInfo = Model::chatLog()->getOneById($chatId);
        if (!$chatId) {
            FF::throwException(Exceptions::RET_CHAT_HELP_NOT_EXIST_ERROR);
        }

        if ($chatInfo['expireTime'] && $chatInfo['expireTime'] < time()) {
            FF::throwException(Exceptions::RET_CHAT_HELP_NOT_EXIST_ERROR);
        }

        if ($uid == $chatInfo['sender']) {
            FF::throwException(Exceptions::RET_CHAT_HELP_SELF_ERROR);
        }
        $helpers = $chatInfo['helpers'] ? explode(',', $chatInfo['helpers']) : [];

        if (count($helpers) >= $chatInfo['helpLimit']) {
            FF::throwException(Exceptions::RET_CHAT_HELP_LIMIT_ERROR);
        }

        if (in_array($uid, $helpers)) {
            FF::throwException(Exceptions::RET_CHAT_HELP_FINISHED_ERROR);
        }
        $helpers[] = $uid;
        $where = ['id' => $chatId, 'updateTime' => $chatInfo['updateTime']];
        $result = Model::chatLog()->update(['helpers' => implode(',', $helpers)], $where);

        if (!$result) {
            FF::throwException(Exceptions::RET_CHAT_HELP_FAIL_ERROR);
        }
        if (count($helpers) < $chatInfo['helpLimit']) {
            Bll::messageNotify()->pushNotifyMsg($chatInfo['sender'], $uid, MessageIds::CLUB_MEMBER_HELP_NOTIFY, [$chatId]);
            return;
        }
        Bll::messageNotify()->pushNotifyMsg($chatInfo['sender'], $chatId, MessageIds::CLUB_PUBLISH_HELP_FINISH_NOTIFY);
        $this->recordHelpReward($chatInfo);
    }

    public function recordHelpReward($chatInfo)
    {
        if (!isset(self::$clubChatHelpRewardTypeMap[$chatInfo['type']])) {
            return;
        }
        $helpers = $chatInfo['helpers'] ? explode(',', $chatInfo['helpers']) : [];
        $extData = [];
        foreach ($helpers as $helper) {
            $extData[] = ['uid' => $helper];
        }

        $type = self::$clubChatHelpRewardTypeMap[$chatInfo['type']];
        $data = [
            'clubId' => $chatInfo['clubId'],
            'uid' => $chatInfo['sender'],
            'set' => $this->makeClubRewardSet($type),
            'type' => $type,
            'progress' => 0,
            'expireTime' => 0,
            'itemList' => $chatInfo['itemList'],
            'extData' => $extData,
        ];

        Model::clubRewards()->insert($data);
    }

    public function makeClubRewardSet($type)
    {
        return $type . microtime(true);
    }
}