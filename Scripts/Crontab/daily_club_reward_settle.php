<?php

namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Bll;
use FF\Factory\Dao;

if (date('H') != '00') {
    return;
}

settleJackpot();
function settleJackpot()
{
    $start = date('Y-m-d 00:00:00');
    $end = date('Y-m-d 23:59:59');

    $coinItem = ITEM_COIN;
    $set = Bll::club()->makeClubRewardSet(ClubBll::CLUB_REWARD_TYPE_JACKPOT);
    $type = ClubBll::CLUB_REWARD_TYPE_JACKPOT;
    $expireTime = strtotime(date('Y-m-d 23:59:59'));
    $sql = "SELECT {$set} as `set`, t1.clubId, t1.uid,{$type} as type ,totalCoin, itemList, extData,{$expireTime} as expireTime FROM club_users t1 JOIN 
(select sum(totalCoin) totalCoin ,JSON_OBJECT('id', {$coinItem}, 'num', sum(totalReward)) as itemList, JSON_ARRAYAGG(JSON_OBJECT('uid', uid, 'coins', totalCoin)) as extData, clubId  from (
SELECT sum(coins) totalCoin,sum(rewardCoins) as totalReward, uid,clubId  FROM  club_jackpot_log where hitTime between '{$start}' and '{$end}' group by clubId, uid) as t
GROUP BY clubId) t2
on t1.clubId = t2.clubId";

    $insertSql = "INSERT INTO club_rewards (`set`, clubId, uid,`type`, totalCoin, itemList, extData, expireTime) VALUES {$sql}";
    Dao::db()->execute($insertSql);
}



