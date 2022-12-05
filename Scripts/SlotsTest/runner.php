<?php
/**
 * 测试执行脚本
 */

namespace FF\Scripts\SlotsTest;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Utils\Log;

include(__DIR__ . '/../common.php');

Log::setOption(array(
    'path' => PATH_LOG . '/SlotsTest'
));

$args = get_cli_args();

$testId = $args['testId'];

$test = Bll::slotsTest()->getTestInfo($testId);

if (!$test || $test['status'] != 1) {
    cli_exit();
}

define('TEST_ID', $testId);

if ($test['betRatio']) define('TEST_BET_RATIO', $test['betRatio']);

define('IV_ENABLE', $test['ivOpened']);

if ($test['isNovice'] != 'A') define('IS_NOVICE', $test['isNovice'] == 'Y');

$gameSeq = $test['gameSeq'] ?? 0;
$machineIds = explode(',', $test['machineIds']);
$machineId = $machineIds[$gameSeq];

if (!file_exists(PATH_ROOT . "/Config/machine/machine-{$machineId}.php")) {
    cli_exit("{$machineId} not support test");
}

$betTimesList = explode(',', $test['perBetTimes']);
$args = array_merge($args, ['machineId' => $machineId, 'betTimes' => $betTimesList[$gameSeq]]);

if (!($initCoins = $test['initCoins'])) {
    $initCoins = array_sum($betTimesList) * $test['totalBet'];
}

//获取玩家数据
$studKey = Keys::slotsTestUserData($testId);
$userData = Dao::redis()->hGetAll($studKey);

$userCnt = $args['betUserCnt'];
$uids = array_keys($userData);
$offset = floor($test['betUsers'] / $args['totalGroup']) * ($args['group'] - 1);

if ($uids) {
    sort($uids);
    $uids = array_slice($uids, $offset, $userCnt);
} else {
    for ($i = 1; $i <= $userCnt; $i++) {
        $uids[] = 100000 + $i + $offset;
    }
}

foreach ($uids as $uid) {
    $userInfo = array_merge(array('level' => $test['userLevel'], 'coinBalance' => $initCoins),
        json_decode($userData[$uid] ?? '[]', true)
    );

    Bll::user()->initVirtualUser($uid, $userInfo);

    $args['uid'] = $uid;
    $tester = new Tester($args);
    $tester->run();

    //同步数据;
    $uInfo = Bll::user()->getCacheData($uid, true);
    $uInfo['exp'] = $uInfo['currLevelExp'];
    unset($uInfo['id'], $uInfo['uid'], $uInfo['currLevelExp']);

    Dao::redis()->hSet($studKey, $uid, json_encode($uInfo));
    Bll::user()->delCacheData($uid);
}

Bll::log()->flushLogs();
Bll::slotsTest()->updateStats($machineId);

$uKey = Keys::slotsTestInfo($testId);

$testedGroupCnt = Dao::redis()->hIncrBy($uKey, 'testedGroupCnt', 1);

if ($testedGroupCnt < $args['totalGroup']) {
    return;
}

Dao::redis()->hDel($uKey, 'testedGroupCnt');

$nexSeq = $gameSeq + 1;
$machineIds = explode(',', $test['machineIds']);

Dao::redis()->hMSet($uKey, ['execStatus' => 0, 'gameSeq' => $nexSeq]);

if (isset($machineIds[$nexSeq])) return;

Bll::slotsTest()->onEnded($testId);
