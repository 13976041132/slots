<?php
/**
 * 数据导入工具
 */

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class Importer
{
    public static function loadCsvToDb($table, $csvFile, $db, $initSqls = null, $truncate = false)
    {
        $targetTable = "t_{$table}";
        $sourceTable = "s_{$table}";

        //读出字段列表
        $reader = new CsvReader($csvFile);
        $fields = $reader->readHeader();
        if (!$fields) FF::throwException(Code::FAILED, 'CSV文件内容为空');
        self::checkFields($table, $fields);

        $dbConfig = Config::get('database', $db);
        if (!$dbConfig) FF::throwException(Code::FAILED, "{$db}库未配置");

        $fieldsSql = [];
        foreach ($fields as $field) {
            $fieldsSql[] = "`{$field}` TEXT";
        }
        $fieldsSql = implode(",\n", $fieldsSql);

        $encoding = $reader->detectEncoding();

        $sqls = array(
            "SET NAMES UTF8",
            "USE {$dbConfig['dbname']}",
            "DROP TABLE IF EXISTS `{$sourceTable}`",
            "CREATE TABLE `{$sourceTable}` (\n{$fieldsSql}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "LOAD DATA LOCAL INFILE '{$csvFile}'\nINTO TABLE `{$sourceTable}`\nCHARACTER SET {$encoding}\nFIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\"' LINES TERMINATED BY '\\n' IGNORE 1 LINES",
        );

        //原始数据初始化
        if ($initSqls) {
            $sqls = array_merge($sqls, $initSqls);
        }

        //清除旧数据
        if ($truncate) {
            $sqls[] = "TRUNCATE TABLE `{$targetTable}`";
        }

        $fieldsConfig = Config::get('tables', $table);
        $fieldsTarget = "`" . implode('`, `', $fieldsConfig[0]) . "`";
        $fieldsSource = "`" . implode('`, `', $fieldsConfig[1]) . "`";

        $sqls[] = "REPLACE INTO `{$targetTable}` ({$fieldsTarget})\n SELECT {$fieldsSource}\n FROM `{$sourceTable}`\n WHERE `{$fields[0]}` IS NOT NULL AND `{$fields[0]}` != ''";
        $sqls[] = "DROP TABLE IF EXISTS `{$sourceTable}`";

        $sql = implode(";\n\n", $sqls) . ';';
        $path = PATH_LOG . '/Sql';
        if (!is_dir($path)) mkdir($path, 0777, true);
        $sqlFile = $path . "/{$sourceTable}.sql";
        file_put_contents($sqlFile, $sql);

        MysqlCli::source($db, $sqlFile);
    }

    private static function checkFields($table, &$fields)
    {
        foreach ($fields as $k => $field) {
            $fields[$k] = str_replace('_id', '_Id', $field);
        }

        $fieldsConfig = Config::get('tables', $table);
        if (!$fieldsConfig) FF::throwException(Code::FAILED, "{$table}表的字段未配置");

        $fieldsRequired = $fieldsConfig[1];
        $fieldsDiff = array_diff($fieldsRequired, $fields);
        if ($fieldsDiff) {
            FF::throwException(Code::FAILED, "CSV表格缺少以下字段: " . implode(', ', $fieldsDiff));
        }
    }
}