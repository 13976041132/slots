<?php
/**
 * 执行测试统计脚本
 */

namespace FF\Scripts\SlotsTest;

use FF\Factory\Bll;

include(__DIR__ . '/../common.php');

$args = get_cli_args();

if (empty($args['testId'])) {
    die('testId参数缺失');
}

Bll::slotsTest()->stats($args['testId']);