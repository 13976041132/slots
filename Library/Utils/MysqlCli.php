<?php
/**
 * mysql-cli工具类
 */

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class MysqlCli
{
    public static function loadCsv($csvFile, $db, $table, $delimiter = ',', $fields = array())
    {
        if (!file_exists($csvFile)) {
            FF::throwException(Code::FAILED, "{$csvFile} is not exists");
        }

        $dbConfig = Config::get('database', $db);
        if (!$dbConfig) {
            FF::throwException(Code::FAILED, "db {$db} is not configured");
        }

        //读出字段列表
        $fp = fopen($csvFile, 'r');
        $fFields = trim(fgets($fp));
        fclose($fp);

        $fFields = explode($delimiter, $fFields);

        $igLines = 0;
        if(!$fields || !array_diff($fFields, $fields) || count($fFields) !== count($fields)) {
            $fields = $fFields;
            $igLines = 1;
        }

        $fields = str_replace($delimiter, ',', implode($delimiter,$fields));

        $sqls = array(
            "SET NAMES UTF8",
            "USE {$dbConfig['dbname']}",
            "LOAD DATA LOCAL INFILE '{$csvFile}' INTO TABLE `{$table}`\nFIELDS TERMINATED BY '{$delimiter}' LINES TERMINATED BY '\\n' IGNORE {$igLines} LINES\n({$fields})"
        );

        $sql = implode(";\n\n", $sqls) . ';';

        $path = PATH_LOG . '/Sql';
        if (!is_dir($path)) mkdir($path, 0777, true);
        $uuid = uniqid(mt_rand());
        $sqlFile = $path . "/sql_{$uuid}.sql";
        file_put_contents($sqlFile, $sql);

        MysqlCli::source($db, $sqlFile);

        unlink($sqlFile);
    }

    public static function source($db, $sqlFile)
    {
        $dbConfig = Config::get('database', $db);
        if (!$dbConfig) FF::throwException(Code::FAILED, "{$db}库未配置");

        $cmd = "mysql -h {$dbConfig['host']} -P {$dbConfig['port']} -u{$dbConfig['username']} -p\"{$dbConfig['passwd']}\" --local-infile=1 {$dbConfig['dbname']} -e \"source {$sqlFile}\"";
        if (!empty($dbConfig['ssl_ca'])) {
            $cmd .= " --ssl-ca={$dbConfig['ssl_ca']} --ssl-mode=VERIFY_CA";
        }

        exec($cmd);
    }

    public static function dump($db, $table, $dumpFile, $options = '')
    {
        $dbConfig = Config::get('database', $db);
        if (!$dbConfig) FF::throwException(Code::FAILED, "{$db}库未配置");

        $cmd = "mysqldump -u{$dbConfig['username']} -h{$dbConfig['host']} -p\"{$dbConfig['passwd']}\" -e -q -c -C -n --add-locks --max_allowed_packet=10240 --set-gtid-purged=OFF";
        if (!empty($dbConfig['ssl_ca'])) {
            $cmd .= " --ssl-ca={$dbConfig['ssl_ca']} --ssl-mode=VERIFY_CA";
        }
        if ($options) {
            $cmd .= ' ' . $options;
        }
        $cmd .= " --databases {$dbConfig['dbname']} --tables {$table} > {$dumpFile}";

        exec($cmd);
    }
}