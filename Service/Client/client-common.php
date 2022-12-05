<?php
/**
 * 客户端程序
 */

namespace FF\Service\Client;

use FF\Factory\Bll;
use FF\Service\Lib\SwooleClient;
use GPBClass\Enum\MSG_ID;
use GPBClass\Enum\RET;

include __DIR__ . '/../../Include/common.php';
include __DIR__ . '/../../Include/consts.php';

$machineId = (int)($argv[1] ?? 10010);
if (!$machineId) die('机台ID参数为空');

$client = new SwooleClient('SlotsGame');

if (!$client->connect()) {
    exit();
}

$client->log('connect');

$uid = 13;
$sessionId = Bll::session()->create($uid, array('uid' => $uid, 'version' => '0'));

$client->send(MSG_ID::MSG_USER_ENTER, array('sessionId' => $sessionId));

while ($data = $client->receive()) {
    $client->log('receive data: ' . json_encode($data, true));
    if (!empty($data['code'])) {
        if ($data['code'] == RET::RET_SESSION_INVALID) {
            $sessionId = Bll::session()->create($uid, array('uid' => $uid, 'version' => '0'));
            $client->send(MSG_ID::MSG_USER_ENTER, array('sessionId' => $sessionId));
            continue;
        } else {
            break;
        }
    }
    switch ($data['msgId']) {
        case MSG_ID::MSG_USER_ENTER:
            $client->send(MSG_ID::MSG_ENTER_MACHINE, array('machineId' => $machineId));
            break;
        case MSG_ID::MSG_ENTER_MACHINE:
            $client->send(MSG_ID::MSG_SLOTS_BETTING, array());
            break;
        default:
            break;
    }
}
