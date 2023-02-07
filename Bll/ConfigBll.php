<?php
/**
 * 配置业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Utils\Config;
use FF\Library\Utils\CsvReader;
use FF\Library\Utils\Importer;
use FF\Library\Utils\Utils;

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

        // 记录操作日志
        if ($oldConfigDiff || $newConfigDiff) {
            Model::operationLog()->addLog(
                \AdminOpCategory::CONFIG, $name, 'update', json_encode(array('old' => $oldConfigDiff, 'new' => $newConfigDiff))
            );
        }
    }

    public function configSync()
    {
        $versions = Dao::redis()->hGetAll(Keys::configVersion());
        if (!$versions) return false;

        $localVersions = Config::get('configVersions', '', false);
        if ($localVersions === null) $localVersions = array();

        $configUpdated = false;
        foreach ($versions as $name => $version) {
            $localVersion = isset($localVersions[$name]) ? $localVersions[$name] : 0;
            if ($localVersion < $version) {
                $config = Dao::redis()->get(Keys::configData($name));
                if ($config) {
                    $this->createConfigFile($name, json_decode($config, true));
                }
                $localVersions[$name] = $version;
                $configUpdated = true;
            }
        }

        if ($configUpdated) {
            $this->createConfigFile('configVersions', $localVersions);
        }

        return $configUpdated;
    }

    public function clearConfigVersion()
    {
        Dao::redis()->del(Keys::configVersion());

        $this->createConfigFile('configVersions', array());
    }

    public function initUltraBetConfig($sourceFile = '')
    {
        $this->createConfigFile('machine/ultra-bet', []);
    }

    public function initItemConfig($sourceFile)
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);

        foreach ($data as $row) {
            $config[$row['Item_Id']] = array(
                'itemId' => $row['Item_Id'],
                'type' => (int)$row['Type'],
                'name' => $row['Item_Name'],
                'duration' => (int)$row['Duration'],
                'buff' => (int)$row['Buff'],
                'options' => json_decode($row['Options'] ?: '{}', true),
            );
        }

        $this->createConfigFile('common/items', $config);
    }

    public function initMachinesConfig()
    {
        $machines = Model::machine()->fetchAll();

        foreach ($machines as $machine) {
            $this->initMachineConfig($machine['machineId']);
        }
    }

    public function initMachineConfig($machineId)
    {
        $dataTypes = [
            MachineBll::DATA_MACHINE_ITEMS,
            MachineBll::DATA_PAYLINES,
            MachineBll::DATA_PAYTABLE,
            MachineBll::DATA_FEATURE_GAMES,
            MachineBll::DATA_ITEM_REEL_WEIGHTS,
        ];

        $machineData = Bll::machine()->getSourceData($machineId, MachineBll::DATA_MACHINE);
        if (!$machineData) return;

        $config = array();
        $config[MachineBll::DATA_MACHINE] = $machineData;

        foreach ($dataTypes as $dataType) {
            $config[$dataType] = Bll::machine()->getSourceData($machineId, $dataType);
            foreach ($config[$dataType] as $k => &$row) {
                if (isset($row['machineId'])) {
                    unset($row['machineId']);
                }
                if (isset($row['md5'])) {
                    unset($row['md5']);
                }
            }
        }

        $this->createConfigFile('machine/machine-' . $machineId, $config);
    }

    public function initBetConfig($sourceFile = '', $version = '')
    {
        $data = Utils::loadCsv($sourceFile);
        $betUnlocks = [];
        foreach ($data as $row) {
            $betUnlocks[] = $row['Value'];
        }

        $config = array(
            'betUnlock' => $betUnlocks,
            'betExpired' => [],
            'betRaise' => [],
            'betPrompt' => [],
            'betGroup' => [],
        );

        $this->createConfigFile('machine/common-bet', $config, $version);
    }

    public function initMachineCollectConfig($sourceFile, $version = '')
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);

        foreach ($data as $row) {
            $seq = $row['Seq'];
            $machineId = $row['Machine_id'];
            $config[$machineId][$seq] = array(
                'target' => $this->parseValue($row['Target']),
                'collectType' => $row['Collect_Type'],
                'collectItem' => $row['Collect_Item'],
                'rewardOptions' => (array)json_decode($row['Reward_Options'], true),
                'activeBetLevel' => (int)$this->parseValue($row['Active_Bet_Level']),
                'resetSpins' => $row['Reset_Spins'] == 'Y',
                'inFreeSpin' => strtoupper($row['In_Freespin']) == 'TRUE',
            );
        }

        $this->createConfigFile('machine/machine-collect', $config, $version);
    }

    public function initJackpotConfig($sourceFile, $version = '')
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);

        foreach ($data as $row) {
            $machineId = (int)$row['Machine_Id'];
            $config[$machineId][$row['ID']] = array(
                'jackpotId' => $row['ID'],
                'jackpotName' => $row['Jackpot_Name'],
                'jackpotType' => 'Jackpot',
                'awardBegin' => $this->parseValue($row['Award_Start']),
                'awardEnd' => $this->parseValue($row['Award_End']),
                'activeBalance' => (array)json_decode($row['Assets'], true),
                'activeLevel' => (array)json_decode($row['Active_Bet_Level'], true),
                'awardByBet' => true,
                'betAddition' => (float)($row['Bet_Addition'] ?? 0),
                'duration' => (int)$row['Duration'],
                'growthMultiple' => 1,
                'relatedMachineIds' => [],
                'collectType' => '',
                'collectItem' => $row['Collect_Item'],
                'target' => 0,
            );
        }

        ksort($config);

        $this->createConfigFile('machine/jackpots', $config, $version);
    }

    public function initWheelConfig($sourceFile)
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);

        foreach ($data as $row) {
            $config[$row['Wheel_id']] = array(
                'wheelName' => $row['Wheel_name'],
                'wheelSpinEnable' => $row['Wheel_Spin_Enable'],
                'nextWheelId' => $row['Next_Wheel_id'],
                'machineId' => $row['Machine_id'],
                'feature' => $row['Feature'],
            );
        }

        $this->createConfigFile('feature/wheels', $config);
    }

    public function initWheelItemConfig($sourceFile, $version = '')
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);

        foreach ($data as $row) {
            $config[$row['Wheel_id']][] = array(
                'pos' => (int)$row['pos'],
                'itemName' => $row['Item_Name'],
                'itemType' => $row['Item_Type'],
                'itemId' => $row['Item_Id'],
                'itemValue' => $this->parseValue($row['Item_Value']),
                'weight' => $row['Weight'],
                'isBackup' => $row['IsBackup']
            );
        }

        $this->createConfigFile('feature/wheel-items', $config, $version);
    }

    public function initHoldAndSpinConfig($sourceFile)
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);
        foreach ($data as $row) {
            $config[$row['Machine_id']][$row['Ball_Num']] = array(
                'bonusElements' => json_decode($row['Ball_Elements'], true),
                'weights' => array(
                    (int)$row['Drop_Weight1'],
                    (int)$row['Drop_Weight2'],
                    (int)$row['Drop_Weight3'],
                    (int)$row['Drop_Weight4'],
                    (int)$row['Drop_Weight5']
                )
            );
        }

        $this->createConfigFile('machine/hold-and-spin', $config);
    }

    public function initBonusValueConfig($sourceFile)
    {
        $config = array();
        $data = Utils::loadCsv($sourceFile);
        $jackpots = Config::get('machine/jackpots');
        foreach ($data as $row) {
            $machineId = $row['Machine_Id'];
            $featureName = $row['Feature_Name'] ?: 'Base';
            $multiples = explode('|', $row['Multiple']);
            $multiples = array_merge($multiples, explode('|', $row['jackpot_id']));

            $weights = explode('|', $row['Weight']);
            foreach ($multiples as $index => $multiple) {
                $multiple = $jackpots[$machineId][$multiple]['jackpotName'] ?? $multiple;
                $config[$machineId][$featureName][$multiple] = (int)($weights[$index] ?? 0);
            }
        }

        $this->createConfigFile('machine/bonus-ball-value', $config);
    }

    protected function getInitConfigSql($table, $machineId)
    {
        $sqls = null;
        $sourceTable = 's_' . $table;

        switch ($table) {
            case 'payline':
                $sqls = $this->getInitPaylineSql($sourceTable);
                break;
            case 'paytable':
                $sqls = $this->getInitPaytableSql($sourceTable);
                break;
            case 'feature_game':
                $sqls = $this->getInitFeatureGameSql($sourceTable);
                break;
            case 'machine_item_reel_weights':
                $sqls = $this->getInitMachineItemReelWeightsSql($sourceTable);
                break;
            default:
                break;
        }

        return $sqls;
    }

    private function getInitMachineItemReelWeightsSql($sourceTable)
    {
        return array(
            "UPDATE `{$sourceTable}` SET Weight = REPLACE(Weight, '|', ',')",
            "UPDATE `{$sourceTable}` SET Feature_Name = 'Base' WHERE Feature_Name = ''",
        );
    }

    private function getInitPaylineSql($sourceTable)
    {
        return array("UPDATE `{$sourceTable}` SET Route = REPLACE(Route, '|', ',')");
    }

    private function getInitPaytableSql($sourceTable)
    {
        $sqls = array(
            "UPDATE `{$sourceTable}` SET Col = REPLACE(Col, '|', ',')",
            "UPDATE `{$sourceTable}` SET Used_in_freespin = 'Y' WHERE Used_in_freespin = 'True'",
            "UPDATE `{$sourceTable}` SET Used_in_freespin = 'N' WHERE Used_in_freespin != 'Y'",
            "DELETE FROM `t_paytable`"
        );

        return $sqls;
    }

    private function getInitFeatureGameSql($sourceTable)
    {
        $sqls = array(
            "UPDATE `{$sourceTable}` SET Trig_item_ID = REPLACE(Trig_item_ID, '，', ',')",
            "UPDATE `{$sourceTable}` SET Trig_item_ID = REPLACE(Trig_item_ID, '{', '')",
            "UPDATE `{$sourceTable}` SET Trig_item_ID = REPLACE(Trig_item_ID, '}', '')",
            "UPDATE `{$sourceTable}` SET Trig_item_ID = REPLACE(Trig_item_ID, ' ', '')",
            "UPDATE `{$sourceTable}` SET Trig_item_ID = '*' WHERE Trig_item_ID = 'all'",
            "UPDATE `{$sourceTable}` SET Trig_item_num = concat(Trig_item_num,'+') WHERE Is_More_Still_Trig = 'TRUE' AND Trig_item_ID != ''",
        );
        return $sqls;
    }
}