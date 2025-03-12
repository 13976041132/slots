<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

if (date('N') != 1 || date('H') != '00') {
    return;
}

$endDate = Bll::club()->getSeasonDate();

//两个时间相差天数
$diff = ceil((strtotime($endDate) - time()) / 86400);

$day = Config::get('club-option', 'season/duration');

if ($diff != $day) {
    return;
}

$clubList = Model::clubs()->fetchAll([], 'clubId');

foreach ($clubList as $info) {
    $key = Keys::puzzle($info['clubId'], $endDate);
    Dao::redis()->del($key);
    $pieces = [1,2,3,4];
    array_shift($pieces);
    Dao::redis()->rPush($key, ...$pieces);
}