<?php

namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Model;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

if (date('H') != '00') {
    return;
}

settleSeasonRank();
settleJackpot();

//½áËãÈü¼¾ÅÅÃû
function settleSeasonRank()
{
    try {
        if (!Bll::clubOption()->isSeasonSettle($seasonId)) {
            return;
        }
        $grades = Config::get('club/grade');
        foreach ($grades as $gradeId => $grade) {
            $type = Bll::rank()->getClubType($gradeId, $seasonId);
            $ranks = Bll::rank()->getList($type, 0, 25);
            if (!$ranks) {
                Log::error('club reward settle error, ranks is empty, gradeId: ' . $gradeId, 'reward.log');
                continue;
            }
            $clubIds = array_keys($ranks);
            $clubList = Model::clubs()->fetchAll(['clubId' => ['in', $clubIds], 'dan' => $gradeId], 'clubId,level');
            $clubList = array_column($clubList, null, 'clubId');
            $rank = 0;
            $clubRankLog = [];
            foreach ($ranks as $clubId => $score) {
                if (!isset($clubList[$clubId])) {
                    continue;
                }
                ++$rank;
                if (Bll::club()->isAiClub($clubId)) {
                    continue;
                }

                $leagueInfo = Bll::clubOption()->getLeagueInfo($gradeId, $rank);
                if (!$leagueInfo) {
                    continue;
                }
                $clubRankLog[] = [
                    'clubId' => $clubId,
                    'rank' => $rank,
                    'poins' => $score,
                    'season' => $seasonId,
                    'time' => now(),
                    'dan' => $gradeId,
                    'rewardCoins' => $leagueInfo['rewardProps']['count']
                ];

                $danId = Bll::clubOption()->getDanIdByDanName($leagueInfo['rewardGrade']);
                if ($danId && $danId != $gradeId) {
                    Bll::clubCache()->updateData($clubId, ['dan' => $danId]);
                }
                $type = ClubBll::CLUB_REWARD_TYPE_RANK;
                $set = Bll::club()->makeClubRewardSet($type);
                $rewardTime = Bll::clubOption()->getClubRewardTime($type, $clubList[$clubId]['level']);
                $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
                $rankType = Bll::rank()->getClubSeasonUserPointType($clubId, $seasonId);
                $userRanks = Bll::rank()->getList($rankType, 0, -1);
                if (!$userRanks) {
                    Log::error('club reward settle error, userRanks is empty, clubId: ' . $clubId, 'reward.log');
                    continue;
                }

                $totalScore = array_sum(array_values($userRanks));
                if ($totalScore == 0) {
                    Log::error('club reward settle error, totalScore is 0, clubId: ' . $clubId, 'reward.log');
                    continue;
                }
                $clubUsersList = Model::clubUsers()->fetchAll(['uid' => ['in', array_keys($userRanks)], 'clubId' => $clubId], 'uid');
                if (!$clubUsersList) {
                    Log::error('club reward settle error, clubUsersList is empty, clubId: ' . $clubId, 'reward.log');
                    continue;
                }
                $uids = array_column($clubUsersList, 'uid');
                $seasonRewardData = [];
                $rewards = $leagueInfo['rewardProps'];
                foreach ($userRanks as $ruid => $userScore) {
                    if (!in_array($ruid, $uids)) {
                        Log::error('club reward settle error, uid not in clubUsersList, clubId: ' . $clubId . ', uid: ' . $ruid, 'reward.log');
                        continue;
                    }
                    $coins = max(ceil($rewards['count'] * $userScore / $totalScore), 10000);
                    $seasonRewardData[] = [
                        'set' => $set,
                        'clubId' => $clubId,
                        'progress' => $rank,
                        'uid' => $ruid,
                        'totalpoints' => $totalScore,
                        'points' => $userScore,
                        'type' => ClubBll::CLUB_REWARD_TYPE_RANK,
                        'totalCoin' => $rewards['count'],
                        'itemList' => json_encode([['id' => $rewards['itemId'], 'num' => $coins]]),
                        'expireTime' => $expireTime,
                        'createTime' => date('Y-m-d H:i:s'),
                    ];
                }
                Model::clubRewards()->insertMulti($seasonRewardData);
            }

            if ($clubRankLog) {
                Model::clubRankLog()->insertMulti($clubRankLog);
            }
        }
    } catch (\Exception $e) {
        Log::error($e->getMessage(), 'reward.log');
    }
}

function settleJackpot()
{
    try {
        $start = yesterday();
        $end = date('Y-m-d 23:59:59', strtotime($start));
        $coinItem = ITEM_COIN;
        $set = Bll::club()->makeClubRewardSet(ClubBll::CLUB_REWARD_TYPE_JACKPOT);
        $createTime = now();
        $type = ClubBll::CLUB_REWARD_TYPE_JACKPOT;
        $rewardTime = Bll::clubOption()->getClubRewardTime($type, 0);
        $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
        $sql = "SELECT '{$set}' as `set`, t1.clubId, t1.uid,{$type} as type ,times, totalCoin, concat('[',itemList,']') as itemList, extData,{$expireTime} as expireTime, '{$createTime}' as createTime FROM club_users t1 JOIN 
(select sum(totalJackpotReward) totalCoin, count(1) as times, sum(totalJackpotReward) totalReward,JSON_OBJECT('id', {$coinItem}, 'num', sum(totalJackpotReward)) as itemList, JSON_ARRAYAGG(JSON_OBJECT('uid', uid, 'times', jacckTimes)) as extData, clubId  from (
SELECT sum(coins) totalJackpotCoin,sum(rewardCoins) as totalJackpotReward,count(1) as jacckTimes, uid,clubId  FROM  club_jackpot_log where hitTime between '{$start}' and '{$end}' group by clubId, uid) as t
GROUP BY clubId) t2
on t1.clubId = t2.clubId";

        $insertSql = "INSERT INTO club_rewards (`set`, clubId, uid,`type`, progress, totalCoin, itemList, extData, expireTime, createTime) {$sql}";
        Dao::db()->execute($insertSql);
    } catch (\Exception $e) {
        Log::error($e->getMessage(), 'reward.log');
    }
}



