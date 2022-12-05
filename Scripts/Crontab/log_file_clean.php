<?php
/**
 * 清除历史日志文件
 */

namespace FF\Scripts\Crontab;

include_once __DIR__ . '/../common.php';

//删除历史日志文件
$date = date('Ymd', time() - 86400 * 7);
$logDir = PATH_LOG . '/' . $date;

if (is_dir($logDir)) {
    $files = array_diff(scandir($logDir), array('.', '..'));
    foreach ($files as $file) {
        unlink("{$logDir}/{$file}");
    }
    rmdir($logDir);
}