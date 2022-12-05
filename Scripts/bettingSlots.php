<?php

namespace FF\Scripts;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Feature;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Factory\MQ;
use FF\Framework\Common\Format;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Library\Sdk\JWT;
use FF\Library\Sdk\JwtSdk;
use FF\Library\Utils\Utils;
use FF\Service\Lib\RPC;
use GPBClass\Enum\MSG_ID;

include_once __DIR__ . '/common.php';

define('TEST_ID', 742);

////获取玩家数据
$studKey = Keys::slotsTestUserData(742);
$userInfo = Dao::redis()->hGet($studKey, 100081);

Bll::user()->initVirtualUser(100081,$userInfo);


$machineObj = Bll::machine()->getMachineInstance(100081, 102, 'general');

$result = $machineObj->calcDiamondCostOfWheelSpin();
var_dump($result);exit;
$times = 0;

while ($times < 20) {
    $result = $machineObj->run(0, ['features' => ['F1020201']]);

    exit;

    if($result['cost']){
        $times++;
    }
    Log::info($result);
    echo $times .PHP_EOL;
}

