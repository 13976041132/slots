<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

if (date('i') != '30' && date('i') != '00') {
    return;
}

$id = Bll::clubOption()->getSeasonId();

if (!$id) {
    return;
}
$seasonInfo = Config::get('club/season', $id, false);
if (!$seasonInfo) {
    return;
}
$gradeCfg = Config::get('club/grade');
$maxDan = $id - 1;
$clubIds = [];
foreach ($gradeCfg as $grade) {
    if ($grade['id'] > $maxDan) {
        continue;
    }
    $key = Bll::rank()->getClubType($grade['id']);
    $rankList = Bll::rank()->getList($key, 0, -1);
    foreach ($rankList as $clubId => $rank) {
        if (!Bll::club()->isAiClub($clubId)) {
            continue;
        }
        $clubIds[] = $clubId;
    }
}

if (!$clubIds) {
    $clubIds = initSeasonClub($maxDan);
}

if (!$clubIds) {
    Log::error("init season club points error", 'club_season.log');
    return;
}

$clubList = Model::clubs()->fetchAll(['clubId' => ['in', $clubIds], 'ai' => 1], 'clubId, dan, aiActLevel');

$danClubs = [];
foreach ($clubList as $club) {
    $danClubs[$club['dan']][$club['aiActLevel']][] = $club['clubId'];
}
$seasonStart = $seasonInfo['seasonStart'];
$time = strtotime($seasonStart);
$today = strtotime(date('Y-m-d'));
$qHours = ($today - $time) / 86400 * 48 + (int)date('H') * 2;
if (date('i') >= 30) {
    $qHours += 1;
}

foreach ($danClubs as $_dan => $levelClubs) {
    foreach ($levelClubs as $_actLevel => $_clubIds) {
        $aiInfo = Bll::clubOption()->getAiCfg($_dan, $_actLevel);
        if (!$aiInfo) {
            continue;
        }
        //计算是否要加积分;
        $pointsTime = $aiInfo['pointsTime'] * 2;
        if ($qHours % $pointsTime != 0) {
            continue;
        }
        $clubKey = Bll::rank()->getClubType($_dan);
        $flag = false;
        foreach ($_clubIds as $_clubId) {
            $points = rand($aiInfo['pointsScope'][0], $aiInfo['pointsScope'][1]);
            Bll::rank()->setScore($_clubId, $clubKey, $points, $flag);
            $flag = true;
        }
    }
}

function initSeasonClub($maxDan)
{
    $clubIds = [];
    $danClubs = [];
    //根据赛季俱乐部信息
    $clubList = Model::clubs()->fetchAll(['ai' => 1, 'dan' => ['<=', $maxDan]], 'clubId, dan, aiActLevel');
    foreach ($clubList as $club) {
        $danClubs[$club['dan']][$club['aiActLevel']][] = $club['clubId'];
    }
    $gradeCfg = Config::get('club/grade');

    foreach ($gradeCfg as $grade) {
        if (!isset($danClubs[$grade['id']])) {
            continue;
        }
        foreach ($grade['aiProportion'] as $inx => $rate) {
            if ($rate == 0) {
                continue;
            }
            $actLevel = $inx + 1;
            if (!isset($danClubs[$grade['id']][$actLevel])) {
                continue;
            }

            $clubs = $danClubs[$grade['id']][$actLevel];
            shuffle($clubs);
            $cnt = min(count($clubs), ceil(count($clubs) * $rate / 10));
            $clubIds = array_merge($clubIds, array_slice($clubs, 0, $cnt));
        }
    }

    return $clubIds;
}


