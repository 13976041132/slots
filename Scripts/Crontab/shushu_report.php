<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Utils\Log;

include __DIR__ . '/../common.php';

$minute = date('Hi', time() - 50);
$key = Keys::shushuList($minute);
$redis = Dao::redis();
$data = [];
$len = 0;
while ($row = $redis->rpop($key)) {
    $row = json_decode($row, true);
    if (!$row) {
        continue;
    }
    $data[] = $row;
    ++$len;
    if ($len < 20) {
        continue;
    }
    Bll::shushu()->batchReportWithRetry($data);
    $len = 0;
    $data = [];
}

Bll::shushu()->batchReportWithRetry($data);