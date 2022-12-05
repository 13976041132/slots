<?php
/**
 * 数据库配置
 */

$config = array(
    DB_CONFIG => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_config',
        'charset' => 'utf8mb4',
    ),
    DB_ADMIN => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_admin',
        'charset' => 'utf8mb4',
    ),

    DB_TEST => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_test',
        'charset' => 'utf8mb4',
    ),

    DB_MAIN => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_main',
        'charset' => 'utf8mb4',
    ),

    DB_LOG => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_logs',
        'charset' => 'utf8mb4',
    ),
    DB_ANALYSIS => array(
        'host' => '192.168.33.10',
        'port' => '3306',
        'username' => 'root',
        'passwd' => '123456',
        'dbname' => 'slots_analysis',
        'charset' => 'utf8mb4',
    ),
);

return $config;