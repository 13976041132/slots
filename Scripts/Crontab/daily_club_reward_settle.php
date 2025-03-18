<?php

namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Bll;
use FF\Factory\Dao;

if (date('H') != '00') {
    return;
}

settleSeasonRank();
settleJackpot();
settleBoxAct();

//½áËãÈü¼¾ÅÅÃû
function settleSeasonRank()
{
    //todo
    if (!Bll::clubOption()->isSeasonSettle($seasonId)) {
        return;
    }
    $type = Bll::rank()->getClubType($seasonId);
    $ranks = Bll::rank()->getList($type, 0,50);

    $clubIds = array_keys($ranks);
    $clubList = Bll::clubCache()->getClubList($clubIds, 'dan');
    $rank = 0;
    foreach ($ranks as $clubId => $score) {
        ++$rank;
        if (!isset($clubList[$clubId])) {
            continue;
        }
        $leagueInfo = Bll::clubOption()->getLeagueInfo($clubList[$clubId]['dan'], $rank);

        if (!$leagueInfo) {
            continue;
        }
        $danId = Bll::clubOption()->getDanIdByDanName($leagueInfo['rewardGrade']);
        if ($danId && $danId != $clubList[$clubId]['dan']) {
            Bll::clubCache()->updateData($clubId, ['dan' => $danId]);
        }
        $type = ClubBll::CLUB_REWARD_TYPE_RANK;
        $set = Bll::club()->makeClubRewardSet($type);
        $rewardTime = Bll::clubOption()->getClubRewardTime($type, $clubList[$clubId]['level']);
        $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;

        $itemList = json_encode([['id' => $leagueInfo['itemId'], 'num' => $leagueInfo['count']]]);
        $sql = "SELECT '{$set}' as `set`, clubId, uid,{$type} as type ,{$leagueInfo['count']} as totalCoin, {$itemList} as itemList,{$expireTime} as expireTime FROM club_users";
        $insertSql = "INSERT INTO club_rewards (`set`, clubId, uid,`type`, totalCoin, itemList, expireTime) {$sql}";
        Dao::db()->execute($insertSql);
    }
}
function settleJackpot()
{
    $start = date('Y-m-d 00:00:00');
    $end = date('Y-m-d 23:59:59');

    $coinItem = ITEM_COIN;
    $set = Bll::club()->makeClubRewardSet(ClubBll::CLUB_REWARD_TYPE_JACKPOT);

    $type = ClubBll::CLUB_REWARD_TYPE_JACKPOT;
    $rewardTime = Bll::clubOption()->getClubRewardTime($type, 0);
    $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
    $sql = "SELECT '{$set}' as `set`, t1.clubId, t1.uid,{$type} as type ,totalCoin, itemList, extData,{$expireTime} as expireTime FROM club_users t1 JOIN 
(select sum(totalCoin) totalCoin ,JSON_OBJECT('id', {$coinItem}, 'num', sum(totalReward)) as itemList, JSON_ARRAYAGG(JSON_OBJECT('uid', uid, 'coins', totalCoin)) as extData, clubId  from (
SELECT sum(coins) totalCoin,sum(rewardCoins) as totalReward, uid,clubId  FROM  club_jackpot_log where hitTime between '{$start}' and '{$end}' group by clubId, uid) as t
GROUP BY clubId) t2
on t1.clubId = t2.clubId";

    $insertSql = "INSERT INTO club_rewards (`set`, clubId, uid,`type`, totalCoin, itemList, extData, expireTime) {$sql}";
    Dao::db()->execute($insertSql);
}

function settleBoxAct()
{
    if (date('N') != 1) {
        return;
    }
    $ymd = yesterday();
}



