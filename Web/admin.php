<?php
/**
 * web入口
 */

use FF\Framework\Core\FF;

$app = 'Admin';

$_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 6);

include(__DIR__ . '/../Include/common.php');
include(__DIR__ . '/../Include/consts.php');

FF::dispatch();