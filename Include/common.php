<?php
/**
 * 通用包含文件
 */

use FF\Framework\Utils\Config;

define('PATH_ROOT', str_replace("\\", "/", realpath(__DIR__ . '/../')));
define('PATH_LIB', PATH_ROOT . '/Library');

if (!isset($app)) $app = 'GameMain';

define('PATH_APP', PATH_ROOT . '/App/' . $app);
define('PATH_RES', PATH_ROOT . '/Web/res');

//加载框架引导文件
include(__DIR__ . '/../Library/Framework/Common/Boot.php');

//加载其他必要文件
include(__DIR__ . '/../Protocol/autoload.php');

$hostUrl = $baseUrl = get_host_url();
if ($app == 'Admin') $baseUrl .= '/admin';

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