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
    public function getClubBoxType($clubId)
    {
        $date = Bll::club()->getCurrBoxActDate();
        return 'Box:' . $clubId. ':' . $date;
    }

    public function getClubMachinePointsType($clubId)
    {
        $date = Bll::club()->getCurrBoxActDate();
        return 'machinePoints:' . $clubId. ':' . $date;
    }

    public function setScore($uuid, $type, $score)
    {
        $key = Keys::rank($type);
        Dao::redis()->zAdd($key, (float)$score, $uuid);
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

    public function getClubType()
    {
        return 'Club';
    }
}