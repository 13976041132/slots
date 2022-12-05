<?php
/**
 * 菜单配置
 */

use \FF\Framework\Core\FF;

$config = array(
    array(
        'name' => '首页',
        'icon' => 'home',
        'uri' => '/index/welcome'
    ),
/*    array(
        'name' => '数据',
        'icon' => 'insert_chart',
        'permGroup' => 'analysis',
        'children' => array(
            array('name' => '在线数据', 'uri' => '/analysis/online'),
            array('name' => '经济数据', 'uri' => '/analysis/economy'),
            array('name' => '下注数据', 'uri' => '/analysis/spin'),
        )
    ),*/
    array(
        'name' => '配置',
        'icon' => 'settings',
        'permGroup' => 'config',
        'children' => array(
            array('name' => '配置总览', 'uri' => '/config/index'),
            array('name' => '机台管理', 'uri' => '/machine/index'),
//            array('name' => '转盘管理', 'uri' => '/config/wheel'),
        )
    ),
    array(
        'name' => '测试',
        'icon' => 'text_fields',
        'permGroup' => 'test',
        'children' => array(
            array('name' => '机台测试', 'uri' => '/slotsTest/index', 'pages' => ['/slotsTest/analysis', '/slotsTest/betLog'], 'hidden' => FF::isProduct()),
        )
    ),
    array(
        'name' => '用户',
        'icon' => 'peoples',
        'permGroup' => 'user',
        'children' => array(
            array('name' => '用户数据', 'uri' => '/user/index', 'pages' => ['/user/balances']),
        )
    ),

    array(
        'name' => '游戏',
        'icon' => 'games',
        'permGroup' => 'game',
        'children' => array(
            array('name' => '下注记录', 'uri' => '/game/betLog'),
        )
    ),

    array(
        'name' => '系统',
        'icon' => 'dvr',
        'permGroup' => 'system',
        'children' => array(
            array('name' => 'Redis', 'uri' => '/system/redis'),
            array('name' => '操作日志', 'uri' => '/system/operationLog'),
        )
    ),

    array(
        'name' => '资源管理',
        'icon' => 'image',
        'uri' => '/resource/index'
    ),

);

return $config;