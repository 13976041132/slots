<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

$clubList = Model::clubs()->fetchAll([], 'clubId');
$actData = Bll::club()->getCurrBoxActDate();
foreach ($clubList as $info) {
    $key = Keys::puzzle($info['clubId'], $actData);
    Dao::redis()->del($key);
    $pieces = [1,2,3,4];
    array_shift($pieces);
    Dao::redis()->rPush($key, ...$pieces);
}