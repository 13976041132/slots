<?php
/**
 * 服务管理器
 */

namespace FF\Service\Bin;

use FF\Framework\Utils\Config;
use FF\Service\Lib\SwooleClient;

use GPBClass\Enum\MSG_ID;

include __DIR__ . '/../../Scripts/common.php';

function run($action)
{
    $args = get_cli_args();
    $servers = Config::get('servers');

    if (!empty($args['server'])) {
        $serverType = $args['server'];
        if (empty($servers[$serverType])) die("Server config is not exists for {$serverType}");
        $servers = array($serverType => $servers[$serverType]);
    }

    foreach ($servers as $serverType => $serverConfig) {
        if ($serverType == 'WebServer') {
            continue;
        }
        foreach ($serverConfig['nodes'] as $node => $nodeInfo) {
            switch ($action) {
                case 'start':
                    start($serverType, $node, $nodeInfo);
                    break;
                case 'stats':
                    stats($serverType, $node);
                    break;
                case 'restart':
                    restart($serverType, $node);
                    break;
                case 'reload':
                    reload($serverType, $node);
                    break;
                case 'stop':
                    stop($serverType, $node);
                    break;
                default:
                    die('action ' . $action . ' is not supported');
            }
        }
        if ($serverType == 'PubSub') {
            sleep(1);
        }
    }
}

function start($serverType, $node, $nodeInfo)
{
    exec("ifconfig | sed -n '/inet /p' | awk '{print $2}'", $addrs);

    //先判断内网IP，再判断外网IP
    if (!in_array($nodeInfo['host'], $addrs)) {
        $hostUrl = Config::get('app-host', ENV . '/' . APP_ID);
        $hostIp = file_get_contents($hostUrl . '/tools/ip.php');
        if ($hostIp != $nodeInfo['host']) {
            return;
        }
    }

    $dir = __DIR__;
    $cmd = "php {$dir}/app.php server={$serverType} node={$node} env=" . ENV;
    exec($cmd, $output);
    foreach ($output as $str) {
        echo $str . PHP_EOL;
    }
}

function stats($serverType, $node)
{
    $client = new SwooleClient($serverType, $node);
    $client->send(MSG_ID::MSG_SERVER_STATS);
    $data = $client->receive();
    $stats = $data['data'];
    $runTime = 0;
    cli_output('----------------------------------');
    foreach ($stats as $key => $value) {
        if ($key == 'start_time') {
            $runTime = time() - $value;
            $value = date('Y-m-d H:i:s', $value);
        }
        cli_output("{$key} = {$value}");
        if ($key == 'start_time') {
            $days = floor($runTime / 86400);
            $hours = floor(($runTime % 86400) / 3600);
            $minutes = floor(($runTime % 3600) / 60);
            $seconds = $runTime % 60;
            cli_output("run_time = {$days}天 {$hours}小时 {$minutes}分 {$seconds}秒");
        }
    }
    cli_output('----------------------------------');
}

function restart($serverType, $node)
{
    $client = new SwooleClient($serverType, $node);
    $client->send(MSG_ID::MSG_SERVER_RESTART);
    cli_output($client->receive());
}

function reload($serverType, $node)
{
    $client = new SwooleClient($serverType, $node);
    $client->send(MSG_ID::MSG_SERVER_RELOAD);
    cli_output($client->receive());
}

function stop($serverType, $node)
{
    $client = new SwooleClient($serverType, $node);
    $client->send(MSG_ID::MSG_SERVER_STOP);
    cli_output($client->receive());
}