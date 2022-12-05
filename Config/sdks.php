<?php
/**
 * SDK配置
 */

$config = array(
    'amazon-s3' => array(
        'version' => 'latest',
        'credentials' => array(
            'key' => '',
            'secret' => '',
        ),
        'region' => 'us-east-1',
        'bucket' => ''
    )
);

return $config;