<?php
/**
 * 重置OpCache缓存
 */

include __DIR__ . '/../../Include/common.php';
include __DIR__ . '/../../Include/auth.php';

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo 'OpCache重置' . ($result ? '成功' : '失败');
} else {
    echo 'OpCache未开启';
}