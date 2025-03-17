<?php

namespace FF\Doc;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;

include __DIR__ . '/../Include/common.php';
include __DIR__ . '/../Include/consts.php';

$tables = Config::get('tables-csv');

$dir = str_replace('\\', '/', __DIR__);

echo PHP_EOL;

$args = get_cli_args();

$uploading = $args['uploading'] ?? false;
$target = $args['target'] ?? ENV;
$csvDir = $dir . '/csv';
$filesMd5 = Config::get('tables-csv-md5-' . $target, false) ?: array();
$changed = false;

echo $target . ' Importing...' . PHP_EOL;

foreach ($tables as $group => $groupFiles) {
    foreach ($groupFiles as $table => $csvFileName) {
        $csvFile = $csvFileName ? $csvDir . '/' . $csvFileName : '';

        if ($csvFileName && !file_exists($csvFile)) {
            echo "{$csvFileName} does't exists!" . PHP_EOL;
            if ($target === ENV) {
                exit(0);
            }

            continue;
        }

        if ($csvFileName) {
            $fileMd5 = md5_file($csvFile);
            $oldMd5 = $filesMd5[$csvFileName] ?: '';
            if ($oldMd5 == $fileMd5) {
                continue;
            }
            $filesMd5[$csvFileName] = $fileMd5;
        }

        echo 'Importing ' . $table . PHP_EOL;

        if ($target == ENV) {
            Bll::config()->initConfigFromFile($table, $csvFile, '', $uploading);
        }

        $changed = true;
    }
}

if ($changed && !$uploading && $target == ENV) {
    Bll::config()->createConfigFile('tables-csv-md5-' . $target, $filesMd5);
}

echo PHP_EOL;
echo '配置表导入完毕' . PHP_EOL;
echo PHP_EOL;
