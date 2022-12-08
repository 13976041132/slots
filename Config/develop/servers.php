<?php
/**
 * 服务配置
 */

$config = array(
    'AsyncTask' => array(
        'nodes' => array(
            array('host' => '127.0.0.1', 'port' => 9640),
        ),
        'mode' => SWOOLE_PROCESS,
        'sock' => SWOOLE_SOCK_TCP,
        'options' => array(
            'worker_num' => 1,
            'task_worker_num' => 2,
            'dispatch_mode' => 2,
            'open_tcp_nodelay' => true,
            'log_file' => PATH_LOG . '/AsyncTask.log',
            'log_level' => 2,
            'daemonize' => 1
        )
    ),

    'GameLog' => array(
        'nodes' => array(
            array('host' => '127.0.0.1', 'port' => 8400),
        ),
        'mode' => SWOOLE_PROCESS,
        'sock' => SWOOLE_SOCK_TCP,
        'options' => array(
            'worker_num' => 1,
            'dispatch_mode' => 2,
            'open_tcp_nodelay' => true,
            'log_file' => PATH_LOG . '/GameLog.log',
            'log_level' => 2,
            'daemonize' => 1
        )
    ),

    'SlotsTest' => array(
        'nodes' => array(
            array('host' => '127.0.0.1', 'port' => 8401),
        ),
        'mode' => SWOOLE_PROCESS,
        'sock' => SWOOLE_SOCK_TCP,
        'options' => array(
            'worker_num' => 1,
            'dispatch_mode' => 2,
            'open_tcp_nodelay' => true,
            'log_file' => PATH_LOG . '/SlotsTest.log',
            'log_level' => 2,
            'daemonize' => 1
        )
    ),
);

return $config;