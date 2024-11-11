<?php
/**
 * 数据库配置
 */

use FF\Framework\Core\FF;

$config = array(
    DB_MAIN => array(
        'host' => FF::getConfig('db.hostname'),
        'port' => FF::getConfig('db.hostport'),
        'username' => FF::getConfig('db.username'),
        'passwd' => FF::getConfig('db.password'),
        'dbname' => FF::getConfig('db.database'),
        'charset' => 'utf8mb4',
    ),
);

return $config;