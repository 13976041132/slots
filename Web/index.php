<?php
/**
 * web入口
 */

use FF\Framework\Core\FF;

header('Access-Control-Allow-Origin:*');

include(__DIR__ . '/../Include/common.php');
include(__DIR__ . '/../Include/consts.php');

FF::dispatch();