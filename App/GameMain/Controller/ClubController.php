<?php

namespace FF\App\GameMain\Controller;

use FF\Constants\Exceptions;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;

class ClubController extends BaseController
{
    //获取俱乐部列表
    public function fetchSuggestList()
    {
        $count = (int)$this->getParam('count', false, 10);
        return array_values(Bll::club()->getSuggestList($count));
    }

    public function searchClubList()
    {
        $keyword = (string)$this->getParam('keyword');
        return Bll::club()->searchClubList($keyword);
    }

    //创建俱乐部
    public function createClub()
    {
        $params = $this->getParams();
        $uid = $this->getUid();
        $clubId = Bll::club()->createClub($uid, $params);
        return Bll::club()->getInfo($clubId);
    }

    //获取俱乐部信息
    public function fetchClubInfo()
    {
        $uid = $this->getUid();
        $clubId = (int)$this->getParam('clubId', false, 0);
        if (!$clubId) {
            $clubId = Bll::club()->getClubIdByUid($uid);
        }
        if (!$clubId) {
            FF::throwException(Exceptions::RET_CLUB_NOT_JOIN_ERROR);
        }
        $info = Bll::club()->getInfo($clubId);
        $rankType = Bll::rank()->getClubChestType($clubId);
        $info['chestPoints'] = Bll::rank()->getTotalScore($rankType);
        return $info;
    }

    //加入俱乐部
    public function joinClub()
    {
        $uid = $this->getUid();
        $clubId = (int)$this->getParam('clubId');
        Bll::club()->joinClub($uid, $clubId);
        return Bll::club()->getInfo($clubId);
    }

    //退出俱乐部
    public function quitClub()
    {
        $uid = $this->getUid();
        Bll::club()->quitClub($uid);
        return [];
    }

    //邀请进入俱乐部
    public function inviteJoinClub()
    {
        $uid = $this->getUid();
        $tuid = (int)$this->getParam('tuid');
        Bll::club()->inviteJoinClub($uid, $tuid);
        return [];
    }

    //接受邀请加入俱乐部
    public function acceptInviteJoinClub()
    {
        $uid = $this->getUid();
        $uuid = (string)$this->getParam('uuid');
        return Bll::club()->acceptInviteJoinClub($uid, $uuid);
    }

    public function refuseInviteJoinClub()
    {
        $uid = $this->getUid();
        $uuid = (string)$this->getParam('uuid');
        Bll::club()->refuseInviteJoinClub($uid, $uuid);
        return [];
    }

    //获取俱乐部成员列表
    public function fetchMemberList()
    {
        $uid = $this->getUid();
        $clubId = (int)$this->getParam('clubId', false,  0);
        $page = (int)$this->getParam('page', false,  1);
        $pageSize = (int)$this->getParam('pageSize', false,  10);
        return Bll::club()->getMemberList($uid, $clubId, $page, $pageSize);
    }

    //解散俱乐部
    public function dissolveClub()
    {
        $uid = $this->getUid();
        Bll::club()->dissolveClub($uid);
        return [];
    }

    //发言
    public function chat()
    {
        $uid = $this->getUid();
        $content = (string)$this->getParam('content');
        Bll::club()->chat($uid, $content);
        $list =  Bll::club()->getChatList($uid);

        return ['list' => $list];
    }

    //修改俱乐部信息
    public function updateClubInfo()
    {
        $uid = $this->getUid();
        $params = $this->getParams();
        $clubId = Bll::club()->updateClubInfo($uid, $params);
        return Bll::club()->getInfo($clubId);
    }

    //设置禁言状态
    public function setMuteStatus()
    {
        $uid = $this->getUid();
        $tuid = (int)$this->getParam('tuid');
        Bll::club()->setMuteStatus($uid, $tuid);
        return [];
    }

    //踢出俱乐部成员
    public function kickOutClubMember()
    {
        $uid = $this->getUid();
        $tuid = (int)$this->getParam('tuid');
        Bll::club()->kickOutClubMember($uid, $tuid);
        return [];
    }

    //成员捐赠金币
    public function donateCoins()
    {
        $uid = $this->getUid();
        $coins = (int)$this->getParam('coins');
        return Bll::club()->donateCoins($uid, $coins);
    }

    //成员积分上报
    public function pointsReport()
    {
        $uid = $this->getUid();
        $points = (int)$this->getParam('points');
        return Bll::club()->pointsReport($uid, $points);
    }

    //获取俱乐部排行榜
    public function fetchClubRankList()
    {
        $uid = $this->getUid();
        $dan = (int)$this->getParam('dan', false,  0);
        $rankList = Bll::rank()->getList(Bll::rank()->getClubType($dan), 0, 99);
        $clubIds = array_keys($rankList);
        if (empty($clubIds)) {
            return [];
        }
        $clubList = Bll::clubCache()->getClubList($clubIds);
        $rank = 1;
        $list = [];
        foreach ($rankList as $clubId => $score) {
            if (empty($clubList[$clubId])) continue;
            $list[] = [
                'points' => (int)$score,
                'rank' => $rank++,
                'clubName' => $clubList[$clubId]['clubName'],
                'clubId' => $clubId,
                'dan' => $clubList[$clubId]['dan'],
                'level' => $clubList[$clubId]['level'],
                'memberCount' => $clubList[$clubId]['memberCnt'],
                'headId' => $clubList[$clubId]['headId'],
            ];
        }
        return ['rankList' => $list, 'myRank' => Bll::club()->getMyClubRank($uid)];
    }

    //掉落拼图碎片
    public function dropPuzzle()
    {
        $uid = $this->getUid();
        $pieceId = Bll::club()->dropPuzzle($uid);
        return ['pieceId' => $pieceId];
    }

    //发布援助
    public function publishHelp()
    {
        $uid = $this->getUid();
        $type = $this->getParam('type');
        return Bll::club()->publishHelp($uid, $type);
    }

    //jackpot上报
    public function jackpotReport()
    {
        $uid = $this->getUid();
        $coins = (int)$this->getParam('coins');
        Bll::club()->jackpotReport($uid, $coins);
        return [];
    }

    //俱乐部机台收集积分上报
    public function machinePointsReport()
    {
        $uid = $this->getUid();
        $points = (int)$this->getParam('points');
        $machineId = (int)$this->getParam('machineId');
        Bll::club()->machinePointsReport($uid, $machineId, $points);
        return [];
    }

    public function fetchMachinePointsRankList()
    {
        $machineId = (int)$this->getParam('machineId');
        return Bll::club()->fetchMachinePointsRankList($this->getUid(), $machineId);
    }

    public function fetchHistoryRankList()
    {
        return Model::clubRankLog()->fetchAll([], '*', ['time' => 'asc']);
    }

    public function fetchClubRewardList()
    {
        $uid = $this->getUid();
        return Bll::club()->getClubRewardList($uid);
    }
    public function fetchClubRewardInfo()
    {
        $uid = $this->getUid();
        $set = (string)$this->getParam('set');
        return Bll::club()->getClubRewardInfo($uid,$set);
    }

    public function claimClubReward()
    {
        $uid = $this->getUid();
        $sets = $this->getParam('sets');
        if (!is_array($sets)) {
            $sets = json_decode($sets);
        }
        if (count($sets) > 100) {
            FF::throwException(Exceptions::PARAM_INVALID_ERROR);
        }
        $itemList = Bll::club()->claimClubReward($uid, $sets);
        return ['itemList' => $itemList];
    }

    public function helpMember()
    {
        $uid = $this->getUid();
        $publishId = (int)$this->getParam('publishId');
        return Bll::club()->helpMember($uid, $publishId);
    }

    public function fetchClubChatList()
    {
        $uid = $this->getUid();
        $lastChatId = (int)$this->getParam('lastChatId',false,0);
        $list = Bll::club()->getChatList($uid, $lastChatId);
        return ['list' => $list];
    }

    public function fetchPublishHelpList()
    {
        $uid = $this->getUid();
        $page = (int)$this->getParam('page', false, 1);
        $pageSize = (int)$this->getParam('pageSize', false, 50);
        $clubId = Bll::club()->getClubIdByUid($uid);
        if ($clubId == 0){
            FF::throwException(Exceptions::RET_CLUB_NOT_EXISTS_ERROR);
        }
        return Bll::club()->getPublishHelpList($clubId, $page, $pageSize);
    }

    public function fetchDanSummary()
    {
        $list = Bll::club()->getDanSummaryData();
        return ['list' => $list];
    }

    public function fetchUserInviteList()
    {
        $uid = $this->getUid();
        $list = Bll::club()->fetchUserInviteList($uid);
        return ['list' => $list];
    }

    public function fetchPuzzleInfo()
    {
        $uid = $this->getUid();
        return Bll::club()->getPuzzleInfo($uid);
    }

    public function fetchMemberInfo()
    {
        $uid = $this->getUid();
        $muid = (int)$this->getParam('muid', false, $uid);
        return Bll::club()->getMemberInfo($muid);
    }
    //获取开发机台的积分信息
    public function fetchGameInfo()
    {
        $uid = $this->getUid();
        return Bll::club()->getGameInfo($uid);
    }
}
