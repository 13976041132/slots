<?php
/**
 * 执行测试任务
 */

namespace FF\Scripts\SlotsTest;

use FF\Factory\Bll;


include(__DIR__ . '/../common.php');

$args = get_cli_args();

$testId = isset($args['testId']) ? $args['testId'] : 0;

if (!$testId) {
    cli_exit('参数错误');
}

try {
    Bll::slotsTest()->run($testId);
} catch (\Exception $e) {
    var_export($e);
}