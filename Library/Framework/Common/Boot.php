<?php
/**
 * 框架引导文件
 */

use FF\Framework\Core\FF;

//记录请求时间
if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}

if (!defined('PATH_ROOT')) die('PATH_ROOT undefined');

//定义框架依赖目录路径
!defined('PATH_APP') && define('PATH_APP', PATH_ROOT);
!defined('PATH_FWK') && define('PATH_FWK', realpath(__DIR__ . '/../'));
!defined('PATH_LOG') && define('PATH_LOG', PATH_ROOT . '/Logs');
!defined('PATH_EXT') && define('PATH_EXT', PATH_ROOT . '/Extend');
!defined('PATH_CFG') && define('PATH_CFG', PATH_ROOT . '/Config');
!defined('PATH_CTRL') && define('PATH_CTRL', PATH_APP . '/Controller');
!defined('PATH_MODEL') && define('PATH_MODEL', PATH_APP . '/Model');
!defined('PATH_VIEW') && define('PATH_VIEW', PATH_APP . '/View');

//定义是否WINDOWS系统
define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0);

//加载几个必要文件
include(PATH_FWK . '/Common/Const.php');
include(PATH_FWK . '/Common/Functions.php');
include(PATH_FWK . '/Common/Handler.php');
include(PATH_FWK . '/Common/Autoload.php');
include(PATH_FWK . '/Core/FF.php');

FF::init();