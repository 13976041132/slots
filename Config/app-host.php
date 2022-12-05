<?php
/**
 * 应用host配置
 */

use \FF\Framework\Common\Env;

$config = array(
    Env::PRODUCTION => array(
        0 => '',
    ),
    Env::TESTING => array(
        0 => '',
    ),
    Env::DEVELOPMENT => array(
        0 => '192.168.33.10:8080',
    )
);

return $config;