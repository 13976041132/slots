<?php

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;

class ClubOptionBll extends Bll
{
    public function getRequestItem($uid)
    {
        $info = Bll::user()->getUserInfo($uid, 'level');
        $config = Config::get('club/request');
        $size = count($config);
        foreach ($config as $idx => $row) {
            if ($info['level'] <= $row['userLevel']) {
                return $row['requestCoin'];
            }
            if ($idx == $size - 1) {
                return $row['requestCoin'];
            }
        }
        return [];
    }
    public function getGameDate()
    {
        $gameCycle = Config::get('club/common', 'gameCycle');
        $date = date('Ymd');
        foreach ($gameCycle as $row) {
            if (count($row) != 2) {
                continue;
            }
            if (($date >= $row[0] && $date <= $row[1]) || $date < $row[0]) {
                return ['startDate'=> $row[0], 'endDate' => $row[1]];
            }
        }
        return [];
    }

    public function isGameOpen()
    {
        $gameCycle = $this->getGameDate();

        if (!$gameCycle) {
            return false;
        }
        $date = date('Ymd');
        return $date >= $gameCycle['startDate'];
    }

    public function getSeasonId()
    {
        $seasonConfig = Config::get('club/season');
        $date = date('Ymd');
        foreach ($seasonConfig as $row) {
            if (count($row) != 2) {
                continue;
            }
            if (($date >= $row[0] && $date <= $row[1])) {
                return $row['id'];
            }
        }
        return 0;
    }
}