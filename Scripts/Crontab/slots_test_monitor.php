<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;

include __DIR__ . '/../common.php';

$key = Keys::slotsTestPlans();

$testIds = Dao::redis()->sMembers($key);

foreach ($testIds as $testId) {

    $iKey = Keys::slotsTestInfo($testId);
    $result = Dao::redis()->hGetAll($iKey);
    $currSeq = $result['gameSeq'] ?? 0;

    $machineIds = explode(',', $result['machineIds']);

    if (!isset($machineIds[$currSeq])) {
        continue;
    }

    $machineId = $machineIds[$currSeq];

    if (!file_exists(PATH_ROOT . "/Config/machine/machine-{$machineId}.php")) {
        continue;
    }

    if (!empty($result['execStatus']) && $result['execStatus'] == 1) {
        continue;
    }

    $lKey = Keys::slotsTestLock($testId);
    if (!Dao::redis()->setnx($lKey, 1)) {
        continue;
    }
    Dao::redis()->expire($lKey, 2);

    Dao::redis()->hSet($iKey, 'execStatus', 1);

    Bll::slotsTest()->machineTestTaskExec($testId);
}
