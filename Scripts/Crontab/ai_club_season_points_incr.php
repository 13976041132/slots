<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

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

$clubList = Model::clubs()->fetchAll(['dan' => ['<=', $id - 1], 'ai' => 1], 'clubId, dan, aiActLevel');

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