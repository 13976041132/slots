<?php
/**
 * 服务应用
 */

namespace FF\Service\Bin;

use FF\Framework\Utils\Config;
use FF\Service\Lib\Service;
use Swoole\Runtime;

include __DIR__ . '/../../Include/common.php';

$args = get_cli_args();

$servers = Config::get('servers');

if (empty($args['server'])) die('参数server缺失');
if (!isset($args['node'])) die('参数node缺失');

$serverType = $args['server'];
$serverNode = $args['node'];

if (!isset($servers[$serverType])) die('参数server无效');
if (!isset($servers[$serverType]['nodes'][$serverNode])) die('参数node无效');

$serverConfig = $servers[$serverType];
$nodeInfo = $serverConfig['nodes'][$serverNode];
$serverConfig['protocolVer'] = $nodeInfo['protocol_ver'] ?? 1;
$serverConfig['host'] = $nodeInfo['host'];
$serverConfig['port'] = $nodeInfo['port'];
$serverConfig['node'] = $serverNode;
unset($serverConfig['nodes']);

Runtime::enableCoroutine(false);

/**
 * @var $className Service
 */
$className = 'FF\\Service\\' . ucfirst($serverType) . 'Server';

$server = $className::getInstance($serverConfig);

call_user_func(array($server, 'start'));