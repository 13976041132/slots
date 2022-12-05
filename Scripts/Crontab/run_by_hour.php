<?php
/**
 * 每小时执行一次的脚本
 */

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Dao;

include_once __DIR__ . '/../common.php';

if (date('H') == '00') {
    include __DIR__ . '/log_file_clean.php';
    //删除历史日志表(最多保留最近14天的日志)
}
