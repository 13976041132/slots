<?php
/**
 * 重新加载服务代码
 */

namespace FF\Service\Bin;

include __DIR__ . '/monitor.php';

run('restart');