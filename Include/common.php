<?php
/**
 * 通用包含文件
 */

use FF\Framework\Utils\Config;

define('PATH_ROOT', str_replace("\\", "/", realpath(__DIR__ . '/../')));
define('PATH_LIB', PATH_ROOT . '/Library');

define('PATH_APP', PATH_ROOT . '/App/GameMain');
define('PATH_RES', PATH_ROOT . '/Web/res');

//加载框架引导文件
include(__DIR__ . '/../Library/Framework/Common/Boot.php');

$hostUrl = $baseUrl = get_host_url();
define('BASE_URL', $baseUrl);
define('JS_URL', $hostUrl . '/js');
define('CSS_URL', $hostUrl . '/css');
define('IMG_URL', $hostUrl . '/image');
define('CDN_URL', '');

define('RES_URL', $hostUrl . '/res');

if (!defined('SWOOLE_PROCESS')) {
    define('SWOOLE_PROCESS', 2);
}
if (!defined('SWOOLE_SOCK_TCP')) {
    define('SWOOLE_SOCK_TCP', 1);
}

$appId = Config::get('app-store', 'appId');

define('APP_ID', $appId);