<?php
/**
 * 数据导出工具
 */

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class Exporter
{
    public static function exportToCsvFromDB($table, $csvFile, $db, $where = '', $encoding = 'UTF8')
    {
        $fieldsConfig = Config::get('tables', $table);
        if (!$fieldsConfig) FF::throwException(Code::FAILED, "{$table}表的字段未配置");
        $dbConfig = Config::get('database', $db);
        if (!$dbConfig) FF::throwException(Code::FAILED, "{$db}库未配置");

        $fields = array();
        foreach ($fieldsConfig[0] as $k => $field) {
            $fields[] = "`{$field}` AS {$fieldsConfig[1][$k]}";
        }
        $fields = '`' . implode('`, `', $fieldsConfig[0]) . '`';
        $titles = "'" . implode("', '", $fieldsConfig[1]) . "'";
        $where = $where ? $where : '1';

        $sqls = array(
            "SET NAMES UTF8",
            "USE {$dbConfig['dbname']}",
            "SELECT * FROM (\n  SELECT {$titles} FROM t_{$table}\n  UNION SELECT {$fields} FROM t_{$table} WHERE {$where}\n) t\nINTO OUTFILE '{$csvFile}'\nCHARACTER SET {$encoding}\nFIELDS TERMINATED BY ','\nOPTIONALLY ENCLOSED BY '\"'\nESCAPED BY '\"'\nLINES TERMINATED BY '\\r\\n'"
        );

        $sql = implode(";\n\n", $sqls) . ';';
        $sqlFile = PATH_LOG . "/t_{$table}.sql";
        file_put_contents($sqlFile, $sql);

        MysqlCli::source($db, $sqlFile);

        if (!file_exists($csvFile)) {
            FF::throwException(Code::FAILED, '导出失败');
        }
    }
}