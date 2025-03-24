<?php

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;

class ClubOptionBll extends Bll
{
    public function getRequestItems($uid)
    {
        $info = Bll::user()->getUserInfo($uid, 'level');
        $config = Config::get('club/request');
        $size = count($config);
        $item = [];
        foreach ($config as $idx => $row) {
            if ($info['level'] <= $row['userLevel']) {
                $item = $row['requestCoin'];
                break;
            }
            if ($idx == $size - 1) {
                $item = $row['requestCoin'];
            }
        }

        if (!$item) {
            return [];
        }

        return [[
            'id' => $item['itemId'],
            'num' => $item['count'],
        ]];
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
                return ['startTime' => strtotime($row[0]), 'endTime' => strtotime('+1days ' . $row[1]) - 1];
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
        return time() >= $gameCycle['startTime'];
    }

    public function getSeasonId()
    {
        $seasonConfig = Config::get('club/season');
        $date = date('Y-m-d');
        foreach ($seasonConfig as $row) {
            if (($date >= $row['seasonStart'] && $date <= $row['seasonEnd'])) {
                return $row['id'];
            }
        }
        return 0;
    }

    public function checkLevelUp($donateTimes, &$level)
    {
        $tmpTimes = 0;
        $isLevelUp = false;
        $config = Config::get('club/level');
        foreach ($config as $row) {
            $tmpTimes += $row['donate'] ?: 1;
            if ($row['clubLevel'] <= $level) {
                continue;
            }
            if ($tmpTimes <= $donateTimes) {
                $level = $row['clubLevel'];
                $isLevelUp = true;
            }
        }

        return $isLevelUp;
    }

    public function isSeasonSettle(&$seasonId)
    {
        $seasonConfig = Config::get('club/season');
        $yesterday = yesterday();
        foreach ($seasonConfig as $row) {
            if ($yesterday == $row['seasonEnd']) {
                $seasonId = $row['id'];
                return true;
            }
        }
        return false;
    }

    public function getLeagueInfo($dan, $rank)
    {
        $config = Config::get('club/league', $dan, false);
        if (!$config) {
            return [];
        }
        foreach ($config as $row) {
            if (count($row['level']) == 1 && $row['level'][0] == $rank) {
                return $row;
            }
            if (count($row['level']) == 2 && ($row['level'][0] <= $rank || $row['level'][1] >= $rank)) {
                return $row;
            }
        }
    }

    public function getDanIdByDanName($danName)
    {
        $config = Config::get('club/grade');
        foreach ($config as $row) {
            if ($row['grade'] == $danName) {
                return $row['gradeId'];
            }
        }
        return 0;
    }

    public function getClubRewardTime($type, $clubLevel)
    {
        $rewardTime = Config::get('club/wall', "{$type}/rewardTime", false);
        $timeLimit = Config::get('club/level', "{$clubLevel}/timeLimit", false);
        $rewardTime = $rewardTime ?: 24;
        $timeLimit = $timeLimit ?: 0;
        return $rewardTime + $timeLimit;
    }

    public function getChestActDate()
    {
        $weekDay = date('N');
        $time = strtotime("+" . (7 - $weekDay) . " days");
        return date('Ymd', $time);
    }

    public function getClubLevelDonateTimes($level, $donateTimes)
    {
        $times = 0;
        $config = Config::get('club/level');
        foreach ($config as $row) {
            if ($row['clubLevel'] <= $level) {
                $times += $row['donate'] ?: 1;
            }
        }
        return max(0, $donateTimes - $times);
    }

    public function isFinishPuzzleNode($node, $pieceNum)
    {
        $config = Config::get('club/activity', $node + 1, false);
        if (!$config) return false;

        return $config['collect'] <= $pieceNum;
    }

    public function getPuzzleNode($node)
    {
        return Config::get('club/activity', $node, false);
    }

    public function getEventNode($node)
    {
        return Config::get('club/events', $node, false);
    }

    public function isFinishEventNode($currPoints, $newPoints, &$node)
    {
        $config = Config::get('club/events');
        if (!$config) return false;

        foreach ($config as $row) {
            if ($row['pointProgress'] > $currPoints && $row['pointProgress'] <= $newPoints) {
                $node = $row['id'];
                return true;
            }
        }
        return false;
    }
}