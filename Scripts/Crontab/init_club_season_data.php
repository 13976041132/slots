<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

//获取参数
$init = $argv[0] ?? false;

if (date('H') != '00' && !$init) {
    return;
}

$id = Bll::clubOption()->getSeasonId();

if (!$id) {
    return;
}

$seasonInfo = Config::get('club/seasons', $id, false);

if (!$init && (!$seasonInfo || $seasonInfo['seasonStart'] != date('Y-m-d'))) {
    return;
}

$clubList = Model::clubs()->fetchAll([], 'clubId');

foreach ($clubList as $clubInfo) {
    Bll::club()->initPuzzle($clubInfo['clubId'], $id);
}