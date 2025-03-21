<?php
/**
 * 排行榜业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;

class RankBll
{
    public function setScore($uuid, $type, $score, $isExpire = true)
    {
        $key = Keys::rank($type);
        $exists = $isExpire ? Dao::redis()->exists($key) : true;
        Dao::redis()->zIncrBy($key, (float)$score, $uuid);

        if (!$exists) {
            Dao::redis()->expire($key, 86400 * 30);
        }
    }

    public function getList($type, $start, $end)
    {
        $key = Keys::rank($type);
        $list = Dao::redis()->zRevRange($key, $start, $end, true);
        return $list ? : array();
    }

    public function getRank($uuid, $type)
    {
        $key = Keys::rank($type);
        $rank = Dao::redis()->zRevRank($key, $uuid);
        return $rank === false ? 0 : ($rank + 1);
    }

    public function getScore($uuid, $type)
    {
        $key = Keys::rank($type);
        return Dao::redis()->zScore($key, $uuid);
    }

    //获取俱乐部赛季时间
    public function getClubType($dan, $seasonId = 0)
    {
        $id = $seasonId ?: Bll::clubOption()->getSeasonId();
        return 'ClubSeason:' . $id . ':' . $dan;
    }

    public function getClubChestType($clubId, $date = 0)
    {
        $date = $date ?: Bll::clubOption()->getChestActDate();
        return 'ClubChest:' . $clubId. ':' . $date;
    }

    public function getClubEventType($clubId, $machineId)
    {
        return 'ClubEvent:' . $clubId . ':' . $machineId . date('Ymd');
    }

    public function getClubSeasonUserPointType($clubId, $seasonId = 0)
    {
        $id = $seasonId ? : Bll::clubOption()->getSeasonId();
        return 'ClubSeason:' . $id . ':' . $clubId;
    }

    public function clearClubRankData($clubId, $dan)
    {
        $keys = [
            Keys::rank($this->getClubSeasonUserPointType($clubId)),
            Keys::rank($this->getClubEventType($clubId, 0)),
            Keys::rank($this->getClubChestType($clubId)),
        ];
        Dao::redis()->del($keys);
        Dao::redis()->zRem(Keys::rank($this->getClubType($dan)), $clubId);
    }

    public function getClubSeasonUserTop($clubId){
        $topInfo = Bll::rank()->getList(Bll::rank()->getClubSeasonUserPointType($clubId), 0, 0);
        return $topInfo ? array_keys($topInfo)[0] : 0;
    }

    public function getTotalScore($type)
    {
        $ranks = $this->getList($type, 0, -1);
        if (!$ranks) {
            return 0;
        }
        return (int)array_sum(array_values($ranks));
    }
}