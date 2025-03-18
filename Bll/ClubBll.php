<?php

namespace FF\Bll;

use Exception;
use FF\App\GameMain\Model\Main\ClubPublishHelpDataModel;
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

    const TYPE_PRIVATE = 1;
    const TYPE_PUBLIC = 2;
    //禁言
    const MUTE_STATUS_ACTIVE = 1;

    const REQUEST_STATUS_INVITE = 1;
    const REQUEST_STATUS_ACCEPT = 2;
    const REQUEST_STATUS_REFUSE = 3;
    const  PUBLISH_HELP_TYPE_COIN = 1;
    private static $publishHelpType = [
        self::PUBLISH_HELP_TYPE_COIN,
    ];

    public static $clubHelpRewardTypeMap = [
        self::PUBLISH_HELP_TYPE_COIN => self::CLUB_REWARD_TYPE_PUBLISH_HELP_COINS
    ];
    const CLUB_REWARD_TYPE_JACKPOT = 1; //jackpot
    const CLUB_REWARD_TYPE_BOX_RANK = 2; //ClubChest
    const CLUB_REWARD_TYPE_PIECE_NODE = 3; //俱乐部活动奖励
    const CLUB_REWARD_TYPE_RANK = 4; //Club League
    const CLUB_REWARD_TYPE_PUBLISH_HELP_COINS = 5; //发布帮助金币奖励
    const CLUB_REWARD_TYPE_PUBLISH_HELP_STAMP = 6;//发布帮助邮票奖励

    const CLUB_REWARD_TYPE_GAME_POINT_RANK = 7; //游戏积分排名

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
            FF::throwException(Exceptions::RET_USER_ALREADY_IN_CLUB_ERROR);
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
            $this->onClubCreateSuccess($clubId);

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
        //获取人数上限
        $memLimit = Config::get('club/level', $clubInfo['level'] . '/member', false);
        if ($clubInfo['memberCnt'] >= $memLimit) {
            FF::throwException(Exceptions::RET_CLUB_MEMBER_LIMIT_ERROR);
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
        Dao::db()->transaction();
        try {
            $flag = Model::clubUsers()->insert(['clubId' => $clubId, 'uid' => $uid]);
            if (!$flag) {
                FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
            }
            $newValue = 0;
            $result = Bll::clubCache()->updateClubByInc($clubInfo['clubId'], 'memberCnt', 1, $newValue);
            if (!$result || $newValue > $memLimit) {
                FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
            }
            Dao::redis()->sAdd(Keys::clubMember($clubId), $uid);
            Dao::db()->commit();
        } catch (Exception $e) {
            Dao::db()->rollback();
            FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
        }
    }

    //拒绝加入俱乐部
    public function refuseInviteJoinClub($uid, $uuid)
    {
        $where = ['uid' => $uid, 'uuid' => $uuid, 'status' => self::REQUEST_STATUS_INVITE];
        $requestInfo = Model::userClubRequestLog()->fetchOne($where);
        if (!$requestInfo) {
            return;
        }
        Model::userClubRequestLog()->update(['status' => self::REQUEST_STATUS_REFUSE], $where);
        Bll::messageNotify()->pushNotifyMsg($requestInfo['invitedBy'], $uid, MessageIds::CLUB_INVITE_JOIN_REFUSE_NOTIFY);
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

        Bll::clubCache()->updateClubByInc($info['clubId'], 'memberCnt', -1);
        Dao::redis()->sRem(Keys::clubMember($info['clubId']), $uid);
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

    public function acceptInviteJoinClub($uid, $uuid)
    {
        $where = ['uid' => $uid, 'uuid' => $uuid];
        $requestInfo = Model::userClubRequestLog()->fetchOne($where);
        if (!$requestInfo || $requestInfo['status'] != self::REQUEST_STATUS_INVITE) {
            FF::throwException(Exceptions::RET_CLUB_JOIN_FAILED_ERROR);
        }
        $this->joinClub($uid, $requestInfo['clubId'], $requestInfo['invitedBy']);
        Model::userClubRequestLog()->update(['status' => self::REQUEST_STATUS_ACCEPT], $where);
        Bll::messageNotify()->pushNotifyMsg($requestInfo['invitedBy'], $uid, MessageIds::CLUB_INVITE_JOIN_SUCCESS_NOTIFY);
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
        $memberList = Model::clubUsers()->fetchAll(['clubId' => $clubId], $fields, ['joinTime' => 'ASC'], '', $pageSize, $offset);
        if (!$memberList) {
            return [];
        }

        $topInfo = Bll::rank()->getList(Bll::rank()->getClubSeasonUserPointType($clubId), 0, 0);
        $pointTop1 = $topInfo ? array_keys($topInfo)[0] : 0;
        $uids = array_column($memberList, 'uid');
        $userList = Bll::user()->getUserInfoList($uids, ['name', 'level', 'headId', 'headFrameId', 'lastOnlineTime']);
        foreach ($memberList as &$member) {
            if (empty($userList[$member['uid']])) {
                continue;
            }
            $roles = $this->getUserRoles($member['uid'], $clubInfo['level'], $pointTop1, $clubInfo['topDonor'], $clubInfo['creator']);
            $member['roleName'] = implode(',', $roles);
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

        Bll::clubCache()->updateClubByInc($info['clubId'],'memberCnt', -1);

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
        //删除俱乐部
        $this->clearClubCacheData($info['clubId']);
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

    public function chat($uid, $content)
    {
        Bll::chatLog()->checkChatContent($content);
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_OPT_NO_PERMISSION_ERROR);
        }

        if ($info['muteStatus'] == self::MUTE_STATUS_ACTIVE) {
            FF::throwException(Exceptions::RET_CHAT_FORBIDDEN_ERROR);
        }
        $userInfo = Bll::user()->getUserInfo($uid, ['name', 'headId', 'headFrameId']);
        $insert = [
            'clubId' => $info['clubId'],
            'content' => $content,
            'sender' => $uid,
            'headId' => $userInfo[$uid]['headId'] ?? 0,
            'headFrameId' => $userInfo[$uid]['headFrameId'] ?? 0,
            'name' => $userInfo[$uid]['name'] ?? 0,
            'chatTime' => time(),
            'microtime' => floor(_microtime()),
        ];
        $chatId = Model::clubChatLog()->insert($insert);
        if (!$chatId) {
            FF::throwException(Exceptions::FAILED);
        }

        $this->cacheChat(array_merge($insert, ['chatId' => $chatId]));
    }

    public function cacheChat($chatData)
    {
        $key = Keys::clubChatInfo($chatData['clubId']);
        Dao::redis()->hMSet($key, ['chatTime' => time(), 'sender' => $chatData['sender']]);
        Dao::redis()->expire($key, 120);
        $chatKey = Keys::clubChatList($chatData['clubId']);
        Dao::redis()->lPush($chatKey, json_encode($chatData));
        if (Dao::redis()->lLen($chatKey) > 200) {
            Dao::redis()->lTrim($chatKey, 0, 200);
        }
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

        if (!Bll::clubCache()->updateData($info['clubId'], $update)) {
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

        $result = Model::clubs()->update(['coins' => ['+=', $coins], 'donateTimes' => ['+=', 1]], ['clubId' => $info['clubId']]);
        if (!$result) {
            FF::throwException(Exceptions::RET_CLUB_DONATE_PROP_FAIL);
        }
        Bll::clubCache()->clean($info['clubId']);
        Model::clubUsers()->update(['coins' => ['+=', $coins]], ['uid' => $uid]);
        $clubInfo = $this->getInfo($info['clubId']);

        $isLevelUp = Bll::clubOption()->checkLevelUp($clubInfo['donateTimes'], $clubInfo['level']);
        if ($isLevelUp) {
            Bll::clubCache()->updateData($clubInfo['clubId'], ['level' => $clubInfo['level']]);
        }
        return $clubInfo;
    }
    public function pointsReport($uid, $points)
    {
        if ($points <= 0) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }
        if (!Bll::clubOption()->getSeasonId()) {
            FF::throwException(Exceptions::RET_CLUB_SEASON_NOT_OPEN_ERROR);
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

        $this->addUserChestPoints($info['clubId'], $uid, $points);
        $this->addUserSeasonPoints($info['clubId'], $uid, $points);
        $this->addSeasonPoints($info['clubId'], $points);
        $seasonPoints = $this->addChestPoints($info['clubId'], $points);
        return [
            'seasonPoints' => (int)$seasonPoints
        ];
    }

    public function addSeasonPoints($clubId, $points)
    {
        Bll::rank()->setScore($clubId, Bll::rank()->getClubType(), $points);
    }

    public function addUserSeasonPoints($clubId, $uid, $points)
    {
        Bll::rank()->setScore($uid, Bll::rank()->getClubSeasonUserPointType($clubId), $points);
    }

    public function addChestPoints($clubId, $points)
    {
        $key = Keys::clubboxPoints($clubId, Bll::clubOption()->getChestActDate());
        return Dao::redis()->incrBy($key, $points);
    }

    public function addUserChestPoints($clubId, $uid, $points)
    {
        Bll::rank()->setScore($uid, Bll::rank()->getClubChestType($clubId), $points);
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

        $key = Keys::clubPuzzle($info['clubId'], Bll::clubOption()->getSeasonId());
        $pieceId = (int)Dao::redis()->lPop($key);
        if (!$pieceId) {
            FF::throwException(Exceptions::RET_CLUB_PUZZLE_FINISH_ERROR);
        }
        $key = Keys::clubPuzzle($info['clubId'], Bll::clubOption()->getSeasonId());
        Dao::redis()->hSet($key, $pieceId, $uid);

        return $pieceId;
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

        $key = Keys::publishHelpTime($uid, $type);
        $coolTime = Config::get('club-option', 'publishHelp/coolTime');
        if (!Dao::redis()->set($key, time(), ['nx', 'ex' => $coolTime])) {
            FF::throwException(Exceptions::RET_CLUB_PUBLISH_HELP_COOL_DOWN);
        }
        $duration = Config::get('club-option', 'publishHelp/duration');
        $helpLimit = Config::get('club-option', 'helpLimit/' . $type);
        $data = [
            'clubId' => $info['clubId'],
            'uid' => $uid,
            'type' => $type,
            'helpLimit' => $helpLimit,
            'itemList' => Bll::clubOption()->getRequestItem($uid),
            'expireTime' => $duration + time(),
        ];

        if (!Model::clubPublishHelpData()->insert($data)) {
            FF::throwException(Exceptions::RET_PUBLISH_HELP_FAIL_ERROR);
        }

        return $this->getPublishHelpList($info['clubId'], 1, 10, $uid);
    }

    public function getPublishHelpList($clubId, $page, $pageSize, $uid = 0)
    {
        $pageSize = max(min($pageSize, 50), 20);
        $offset = ($page > 0 ? ($page - 1) : 0) * $pageSize;
        $where = ['clubId' => $clubId, 'expireTime' => ['>', time()]];
        if ($uid) {
            $where['uid'] = $uid;
        }
        $info = Model::clubPublishHelpData()->fetchOne($where, 'count(1) as count');
        $data = [
            'list' => [],
            'total' => $info['count'],
            'page' => $page,
            'pageSize' => $pageSize,
        ];
        if (!$info['count']) {
            return $data;
        }
        $list = Model::clubPublishHelpData()->fetchAll($where, null, ['id' => 'desc'], [], $pageSize, $offset);
        $helpers = [];
        foreach ($list as $row) {
            $helpers = array_merge($helpers, explode(',', $row['helpers']));
        }
        $helpers = array_flip(array_filter($helpers));
        $userList = Bll::user()->getUserInfoList(array_keys($helpers), 'name,headId,headFrameId');
        foreach ($list as &$row) {
            $row['publishId'] = $row['id'];
            unset($row['id']);
            $row['itemList'] = json_decode($row['itemList'], true) ?: [];
            if (!$row['helpers']) continue;
            $helperIds = explode(',', $row['helpers']);
            $helperList = [];
            foreach ($helperIds as $helperId) {
                if (!isset($userList[$helperId])) {
                    continue;
                }
                $helperList[] = $userList[$helperId];
            }
            $row['helpers'] = $helperList;
        }
        $data['list'] = $list;

        return $data;
    }

    public function jackpotReport($uid, $coins)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            return;
        }

        $bonusRate = Config::get('club/common', 'coefficient');
        $data = [
            'clubId' => $clubId,
            'uid' => $uid,
            'coins' => $coins,
            'rewardCoins' => max(1, floor($coins * $bonusRate)),
        ];
        Model::clubJackpotLog()->insert($data);
    }

    public function machinePointsCollect($uid, $machineId, $points)
    {
        if (!Bll::clubOption()->getGameDate()) {
            return [];
        }
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            return [];
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            return [];
        }
        $rankType = Bll::rank()->getClubEventType($info['clubId'], $machineId);

        Bll::rank()->setScore($uid, $rankType, $points);
        $key = Keys::clubMachinePointData($info['clubId']);
        Dao::redis()->hIncrBy($key, $machineId, $points);
    }

    public function fetchMachinePointsRankList($uid, $machineId)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        $rankType = Bll::rank()->getClubEventType($info['clubId'], $machineId);
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

        return ['rankList' => $list, 'myRank' => $this->getMyPointRank($uid, $info['clubId'], $machineId)];
    }

    public function getMyClubRank($uid)
    {
        $myClubId = Bll::club()->getClubIdByUid($uid);
        return $myClubId ? Bll::rank()->getRank($myClubId, Bll::rank()->getClubType()) : 0;
    }

    public function getMyPointRank($uid, $clubId, $machineId)
    {
        $rankType = Bll::rank()->getClubEventType($clubId, $machineId);
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
                'set' => (string)$reward['set'],
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
        switch ($rewardInfo['type']) {
            case self::CLUB_REWARD_TYPE_JACKPOT:
                $userStatList = $this->getUserJackpotStat($clubId, $rewardInfo['createTime']);
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

    public function getUserJackpotStat($clubId, $settleTime)
    {
        $yesterday = strtotime('-1 day', strtotime($settleTime));
        $date = date('Y-m-d 00:00:00', $yesterday);
        $key = Keys::clubUserJackpotStat($clubId, $date);
        $list = Dao::redis()->get($key);
        if ($list) {
            return json_decode($list, true);
        }

        $endData = date('Y-m-d 23:59:59', $yesterday);
        $where = ['hitTime' => ['between', [$date, $endData]], 'clubId' => $clubId];
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

    public function helpMember($uid, $publishId)
    {
        $publishInfo = Model::clubPublishHelpData()->getOneById($publishId);
        $helpers = [];
        $status = ClubPublishHelpDataModel::PUBLISH_HELP_STATUS_ING;
        do {
            if (!$publishInfo) {
                FF::throwException(Exceptions::RET_CHAT_HELP_NOT_EXIST_ERROR);
            }
            if ($uid == $publishInfo['uid']) {
                FF::throwException(Exceptions::RET_CHAT_HELP_SELF_ERROR);
            }
            $helpers = $publishInfo['helpers'] ? explode(',', $publishInfo['helpers']) : [];

            if ($publishInfo['expireTime'] && $publishInfo['expireTime'] < time()) {
                $status = ClubPublishHelpDataModel::PUBLISH_HELP_STATUS_FAIL;
                break;
            }
            if (count($helpers) >= $publishInfo['helpLimit']) {
                $status = ClubPublishHelpDataModel::PUBLISH_HELP_STATUS_FINISH;
                break;
            }
            if (in_array($uid, $helpers)) {
                break;
            }
            $helpers[] = $uid;
            $where = ['id' => $publishId, 'updateTime' => $publishInfo['updateTime']];
            $result = Model::clubPublishHelpData()->update(['helpers' => implode(',', $helpers)], $where);

            if (!$result) {
                break;
            }
            if (count($helpers) < $publishInfo['helpLimit']) {
                Bll::messageNotify()->pushNotifyMsg($publishInfo['uid'], $uid, MessageIds::CLUB_MEMBER_HELP_NOTIFY, [$publishId]);
            } else {
                Bll::messageNotify()->pushNotifyMsg($publishInfo['uid'], $publishInfo, MessageIds::CLUB_PUBLISH_HELP_FINISH_NOTIFY);
                $this->recordHelpReward($publishInfo);
            }
        } while (0);

        $publishUserInfo = Bll::user()->getUserInfo($publishInfo['uid'], ['name', 'level', 'headId', 'headFrameId']);
        $helperList = Bll::user()->getUserInfoList($helpers, ['uid', 'name', 'headId', 'headFrameId']);
        return [
            'helperList' => array_values($helperList),
            'helpLimit' => $publishInfo['helpLimit'],
            'publishId' => $publishId,
            'status' => $status,
            'publishUserInfo' => $publishUserInfo,
        ];
    }

    public function getChatList($uid, $lastChatId = 0)
    {
        $info = Model::clubUsers()->getOneById($uid);
        if (!$info) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($info['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        $chatKey = Keys::clubChatList($info['clubId']);
        $list = Dao::redis()->lRange($chatKey, 0, 99);

        foreach ($list as $key => $row) {
            $row = json_decode($row, true);
            if ($lastChatId >= $row['chatId']) {
                unset($list[$key]);
                continue;
            }
            $list[$key] = $row;
        }
        array_multisort(array_column($list, 'chatId'), SORT_ASC, $list);

        return $list;
    }

    public function recordHelpReward($publishInfo)
    {
        $helpers = $publishInfo['helpers'] ? explode(',', $publishInfo['helpers']) : [];
        $extData = [];
        foreach ($helpers as $helper) {
            $extData[] = ['uid' => $helper];
        }
        $type = self::$clubHelpRewardTypeMap[$publishInfo['type']];
        $data = [
            'clubId' => $publishInfo['clubId'],
            'uid' => $publishInfo['uid'],
            'set' => $this->makeClubRewardSet($type),
            'type' => $type,
            'progress' => 0,
            'expireTime' => 0,
            'itemList' => $publishInfo['itemList'],
            'extData' => $extData,
        ];

        Model::clubRewards()->insert($data);
    }

    public function makeClubRewardSet($type)
    {
        return $type . microtime(true) * 10000 . mt_rand(1000, 9999);
    }

    public function getDanSummaryData()
    {
        $key = Keys::clubDanStat();
        $data = Dao::redis()->hGetAll($key);
        $list = [];
        foreach ($data as $k => $v) {
            $list[] = ['dan' => (int)$k, 'count' => (int)$v];
        }
        if (!$list) {
            $list = Model::clubs()->fetchAll([], 'count(1) count,dan', [], 'dan');
            $data = array_column($list, 'count', 'dan');
            Dao::redis()->hMSet($key, $data);
        }
        return $list;
    }

    public function fetchUserInviteList($uid)
    {
        $where = ['uid' => $uid, 'status' => self::REQUEST_STATUS_INVITE];
        $list = Model::userClubRequestLog()->fetchAll($where, null, 'inviteTime desc', [], 20);
        $invitedBys = array_column($list, 'invitedBy');
        $invitedByList = Bll::user()->getUserInfoList($invitedBys, ['name', 'headId', 'headFrameId']);

        foreach ($list as $key => $row) {
            if (!isset($invitedByList[$row['invitedBy']])) {
                unset($row[$key]);
                continue;
            }
            $list[$key]['inviterName'] = $invitedByList[$row['invitedBy']]['name'];
            $list[$key]['inviterHId'] = $invitedByList[$row['invitedBy']]['headId'];
            $list[$key]['inviterHFrameId'] = $invitedByList[$row['invitedBy']]['headFrameId'];
        }

        return $list;
    }

    public function getPuzzleInfo($uid)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $key = Keys::clubPuzzle($clubId, Bll::clubOption()->getSeasonId());
        $data = Dao::redis()->hGetAll($key);
        $pieces = array_keys($data);
        $myPieces = [];
        foreach ($data as $pieceId => $_uid) {
            if ($_uid == $uid) {
                $myPieces[] = $pieceId;
            }
        }
        return ['pieces' => $pieces, 'myPieces' => $myPieces];
    }

    public function getMemberInfo($muid)
    {
        $memberInfo = Model::clubUsers()->fetchOne(['uid' => $muid]);
        if (!$memberInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $clubInfo = $this->getInfo($memberInfo['clubId']);
        if (!$clubInfo) {
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }

        $topInfo = Bll::rank()->getList(Bll::rank()->getClubSeasonUserPointType($clubInfo['clubId']), 0, 0);
        $pointTop1 = $topInfo ? array_keys($topInfo)[0] : 0;
        $roles = $this->getUserRoles($memberInfo['uid'], $clubInfo, $pointTop1);
        $key = Keys::publishHelpTime($muid, self::PUBLISH_HELP_TYPE_COIN);
        $ttl = Dao::redis()->ttl($key);
        $userInfo = Bll::user()->getUserInfo($muid, ['name', 'level', 'headId', 'headFrameId', 'lastOnlineTime']);
        return [
            'phCoolTime' => $ttl > 0 ? time() + $ttl : 0,
            'points' => $memberInfo['points'],
            'isOnline' => Bll::user()->isOnlineByLoginTime($userInfo['lastOnlineTime']),
            'roleName' => implode(',', $roles),
            'name' => $userInfo['name'],
            'level' => $userInfo['level'],
            'headId' => $userInfo['headId'],
            'headFrameId' => $userInfo['headFrameId'],
            'muid' => $muid,
        ];
    }

    public function getGameInfo($uid)
    {
        $clubId = $this->getClubIdByUid($uid);
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $gameCycle = Bll::clubOption()->getGameDate();

        if (!$gameCycle) {
            return [];
        }

        $key = Keys::clubMachinePointData($clubId);
        $data = Dao::redis()->hGetAll($key);
        $list = [];
        $machineList = Config::get('club/common', 'machineList');
        foreach ($machineList as $machineId) {
            $list[] = ['machineId' => $machineId, 'points' => $data[$machineId] ?? 0];
        }
        $isOpen = Bll::clubOption()->isGameOpen();
        $shortEndTime = $isOpen ? strtotime(date('Y-m-d 23:59:59')) : 0;
        return array_merge($gameCycle, ['isOpen' => $isOpen, 'shortEndTime' => $shortEndTime, 'machinePoints' => $list]);
    }
    protected function getUserRoles($uid, $clubInfo, $topPoint)
    {
        $roles = [];
        $clubRoles = Config::get('club/levels', $clubInfo['level'] . '/title', false);
        if (!$clubRoles) {
            return [];
        }
        foreach ($clubRoles as $clubRole) {
            switch (strtolower($clubRole)) {
                case 'leader':
                    if ($uid == $clubInfo['creator']) {
                        $roles[] = $clubRole;
                    }
                    break;
                case 'points mvp':
                    if ($topPoint == $uid) {
                        $roles[] = $clubRole;
                    }
                    break;
                case 'donate mvp':
                    if ($clubInfo['topDonor'] == $uid) {
                        $roles[] = $clubRole;
                    }
                    break;
                default:
                    break;
            }
        }
        return $roles ?: ['Member'];
    }

    public function initPuzzle($clubId, $seasonId)
    {
        if ($seasonId == 0) {
            return;
        }
        $pieces = range(1, 42);
        $key = Keys::clubPuzzle($clubId, $seasonId);
        Dao::redis()->del($key);
        array_shift($pieces);
        Dao::redis()->rPush($key, ...$pieces);
    }

    public function clearClubCacheData($clubId)
    {
        $keys = [
            Keys::clubMember($clubId),
            Keys::clubDanStat(),
            Keys::clubUserJackpotStat($clubId, date('Ymd')),
            Keys::clubMachinePointData($clubId),
            Keys::clubInfo($clubId),
            Keys::clubPuzzle($clubId, Bll::clubOption()->getSeasonId()),
            Keys::clubboxPoints($clubId, 1),
        ];

        Dao::redis()->del(...$keys);
        Bll::rank()->clearClubRankData($clubId);
    }

    public function onClubCreateSuccess($clubId)
    {
        Dao::redis()->del(Keys::clubDanStat());
        $this->initPuzzle($clubId, Bll::clubOption()->getSeasonId());
    }
}