<?php

namespace FF\Scripts;

use FF\Factory\Bll;

include_once __DIR__ . '/common.php';

$machineObj = Bll::machine()->getMachineInstance(10004, 10001);
$times = 1;
while(true){
    $result = $machineObj->run(0);
    if($times > 100) break;
    $times++;
}

$result = $machineObj->run(0);

var_dump($result);exit;
