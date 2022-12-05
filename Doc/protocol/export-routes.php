<?php
/**
 * 修改生成的proto类文件
 */

include(__DIR__ . '/../../Protocol/GPBClass/Enum/MSG_ID.php');

$routesCfg = include(__DIR__ . '/../../Config/routes.php');

foreach ($routesCfg as $msgId => $v) {
	$routesCfg[$msgId] = $v[1];
}

ksort($routesCfg);

$json = json_encode($routesCfg, JSON_PRETTY_PRINT);

file_put_contents(__DIR__ . '/proto/routes.json', $json);