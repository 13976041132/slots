<?php
/**
 * 配置业务逻辑
 */

namespace FF\Bll;

use FF\Framework\Utils\Config;
use FF\Library\Utils\CsvReader;
use FF\Library\Utils\Importer;

class ConfigBll
{
    public function parseValue($value)
    {
        $value = trim($value);
        $_value = str_replace(',', '', $value);

        if (is_numeric($_value)) {
            if ((float)$_value == (int)$_value) {
                return (int)$_value;
            } else {
                return (float)$_value;
            }
        } elseif (substr($_value, -1) == '%') {
            $num = (float)substr($_value, 0, -1);
            return $num / 100;
        }

        $data = json_decode($value, true);
        if (is_array($data)) {
            return $data;
        }

        return $value;
    }

    public function checkCsvFile($csvFile)
    {
        $reader = new CsvReader($csvFile);
        $reader->readHeader();
        $reader->close();
    }

    public function initConfigFromFile($table, $sourceFile = '', $machineId = '', $uploading = false)
    {
        if ($sourceFile) $this->checkCsvFile($sourceFile);

        $cfgTable = implode('', array_map('ucfirst', explode('_', $table)));
        $method = 'init' . $cfgTable . 'Config';

        if ($table !== 'machine' && method_exists($this, $method)) {
            $this->$method($sourceFile);
        } else {
            $initSqls = $this->getInitConfigSql($table, $machineId);
            Importer::loadCsvToDb($table, $sourceFile, DB_CONFIG, $initSqls, !$machineId);
            $this->initMachinesConfig();
        }
    }

    public function createConfigFile($name, $config, $version = '')
    {
        if ($version) {
            $name .= '-' . $version;
        }

        // 配置修改前数据
        $oldConfig = Config::get($name, null, false) ?: array();
        $oldConfigDiff = array_deep_diff($oldConfig, $config);
        $newConfigDiff = array_deep_diff($config, $oldConfig);

        $file = PATH_CFG . '/' . $name . '.php';
        file_put_contents($file, "<?php\nreturn " . var_export($config, true) . ";");
        Config::set($name, null, $config);

        if (strripos($name, 'tables-csv-md5') !== false) return;
    }

}