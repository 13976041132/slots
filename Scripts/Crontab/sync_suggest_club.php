<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

$minuter = (int)date('i');
//10分钟运行一次
if ($minuter % 10 != 0) {
    return;
}

$clubList = Model::clubs()->fetchAll([], 'clubId, level, memberCnt');
foreach ($clubList as $key => $club) {
    $memLimit = Config::get('club/level', $club['level'] . '/member', false);
    if ($memLimit && $club['memberCnt'] >= $memLimit) {
        unset($clubList[$key]);
    }
}
$key = Keys::suggestClubSet();
Dao::redis()->del($key);
$clubIds = array_column($clubList, 'clubId');
if ($clubIds) {
    Dao::redis()->sAdd($key, ...$clubIds);
}