<?php
/**
 * web入口
 */

use FF\Framework\Core\FF;

if ($_SERVER['REQUEST_URI'] == '/admin') {
    header('location: /admin/');
    exit();
}

header('Access-Control-Allow-Origin:*');

include(__DIR__ . '/../Include/common.php');
include(__DIR__ . '/../Include/consts.php');

FF::dispatch();