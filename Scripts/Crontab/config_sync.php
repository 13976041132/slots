<?php
/**
 * 配置同步脚本
 */

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;

include __DIR__ . '/../common.php';

function reset_opcache()
{
    //获取本机IP
    $ch = curl_init('http://triplewin.slots.51chivalry.com/tools/ip.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ip = curl_exec($ch);
    curl_close($ch);

    if (!$ip) return;

    $auth = Config::get('servers', 'WebServer/auth');

    //重置本机OpCache
    $ch = curl_init("http://{$ip}/tools/ocp_reset.php");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$auth['user']}:{$auth['pass']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);

    Log::info([$ip, $output], 'crontab.log');
}

//每10s探测一次配置变动
$interval = 10;
$times = 60 / $interval;

for ($i = 0; $i < $times; $i++) {
    $time = time();
    $result = Bll::config()->configSync();
    if ($result) {
        reset_opcache();
    }
    $cost = time() - $time;
    sleep($interval - $cost);
}