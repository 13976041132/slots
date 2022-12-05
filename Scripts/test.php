<?php

namespace FF\Scripts;

use FF\Factory\Bll;

include_once __DIR__ . '/common.php';

$machineObj = Bll::machine()->getMachineInstance(10004, 102);

$result = $machineObj->run(0);

var_dump($result);exit;
