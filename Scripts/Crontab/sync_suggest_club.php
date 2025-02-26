<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

$clubList = Model::clubs()->fetchAll([], 'clubId');
$key = Keys::suggestClubSet();
Dao::redis()->del($key);
$clubIds = array_column($clubList, 'clubId');
if ($clubIds) {
    Dao::redis()->sAdd($key, ...$clubIds);
}


