<?php
/**
 * redis配置
 */

use FF\Framework\Core\FF;

$config = array(
    'main' => array(
        'host' => FF::getConfig('redis.host'),
        'port' => FF::getConfig('redis.port'),
        'auth' => FF::getConfig('redis.password'),
    ),
);

return $config;