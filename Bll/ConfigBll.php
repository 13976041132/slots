<?php
/**
 * 配置业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Model;
use FF\Framework\Utils\Config;
use FF\Library\Utils\CsvReader;
use FF\Library\Utils\Importer;
use FF\Library\Utils\Utils;

class ConfigBll
{

    private function parseDate($dateStr)
    {
        $parts = explode('|', $dateStr);
        return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
    }

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

        if (method_exists($this, $method)) {
            $this->$method($sourceFile);
        }
    }

    public function createConfigFile($name, $config, $version = '')
    {
        if ($version) {
            $name .= '-' . $version;
        }

        $file = PATH_CFG . '/' . $name . '.php';
        file_put_contents($file, "<?php\nreturn " . var_export($config, true) . ";");
        Config::set($name, null, $config);

        if (strripos($name, 'tables-csv-md5') !== false) return;
    }

    public function initActivityConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['node'] = (int)$record['Node'];
            $row['collect'] = (int)$record['Collect'];

            // 解析 NodeReward
            $row['nodeRewards'] = array_map(function ($reward) {
                $parts = explode(',', $reward);
                return [
                    'itemId' => (int)$parts[0], // 道具 ID
                    'count' => (int)$parts[1], // 道具数量
                ];
            }, explode('|', $record['NodeReward']));

            $nodeProps = explode('|',$record['NodeProps']);
            $row['nodeProps'] = [
                'itemId' => (int)$nodeProps[0] ?? 0, // 道具 ID
                'count' => (int)$nodeProps[1] ?? 0, // 道具数量
            ];

            $config[$row['node']] = $row;
        }
        $this->createConfigFile('club/activity', $config);
    }

    public function initChestConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];
            $row['chestLevel'] = (int)$record['ChestLevel'];
            $row['pointProgress'] = (int)$record['PointProgress'];

            $row['chestCoin'] = (int)str_replace(',', '', $record['ChestCoin']);
            // 解析道具奖励
            $row['chestProps'] = array_map(function ($prop) {
                $parts = explode(',', $prop);
                return [
                    'itemId' => (int)$parts[0], // 道具 ID
                    'count' => (int)$parts[1], // 道具数量
                ];
            }, explode('|', $record['ChestProps']));

            $config[$row['chestLevel']] = $row;
        }
        $this->createConfigFile('club/chest', $config);
    }

    public function initCommonConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $config['id'] = (int)$record['ID'];
            $config['unlock'] = (int)$record['Unlock'];
            $config['createCoin'] = (int)$record['CreateCoin'];

            // 解析多人活动开放周期（int[][]）
            $config['gameCycle'] = array_map(function ($cycle) {
                return array_map('intval', explode(',', $cycle));
            }, explode('|', $record['GameCycle']));

            $config['coefficient'] = (float)$record['Coefficient'];
            $config['probability'] = (float)$record['Probability'];
            $config['betLimit'] = (int)$record['BetLimit'];
            $config['collectLimit'] = (int)$record['CollectLimit'];

            // 解析机台活动列表（int[]）
            $config['machineList'] = array_map('intval', explode('|', $record['MachoneList']));

            $config['machineCycle'] = (int)$record['MachoneCycle'];
            $config['aiClub'] = (int)$record['AiClub'];

            // 解析各段位匹配比例（int[][]）
            $config['scale'] = array_map(function ($ratio) {
                return array_map('intval', explode(',', $ratio));
            }, explode('|', $record['Scale']));
        }
        $this->createConfigFile('club/common', $config);
    }

    public function initEventsConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];
            $row['pointProgress'] = (int)$record['PointProgress'];

            // 解析节点奖励（int[][]）
            $row['nodeReward'] = array_map(function ($reward) {
                $parts = explode(',', $reward);
                return [
                    'itemId' => (int)$parts[0], // 道具 ID
                    'count' => (int)$parts[1], // 奖励数量
                ];
            }, explode('|', $record['NodeReward']));

            // 解析节点道具奖励（int[]）
            $row['nodeProps'] = array_map(function ($prop) {
                $parts = explode('|', $prop);
                return [
                    'itemId' => (int)$parts[0], // 道具 ID
                    'count' => (int)$parts[1], // 道具数量
                ];
            }, [$record['NodeProps']]);

            $config[$row['id']] = $row;
        }
        $this->createConfigFile('club/events', $config);
    }

    public function initGradeConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'] - 1;
            $row['gradeId'] = (int)$record['Grade_Id'];
            $row['grade'] = $record['Grade'];

            // 解析宝箱奖励加成（float）
            $row['chestAddition'] = $record['ChestAddition'] !== '' ? (float)$record['ChestAddition'] : 0;

            // 解析俱乐部活动奖励加成（float）
            $row['activityAddition'] = $record['ActivityAddition'] !== '' ? (float)$record['ActivityAddition'] : 0;

            // 解析周边系统权益（int[]）
            $row['systemAddition'] = $record['SystemAddition'] !== '' ? array_map('intval', explode('|', $record['SystemAddition'])) : [];

            // 解析膨胀系数（int）
            $row['expansion'] = $record['Expansion'] !== '' ? (int)$record['Expansion'] : 0;

            // 解析AI俱乐部匹配比例（int[]）
            $row['aiProportion'] = $record['Ai_Proportion'] !== '' ? array_map('intval', explode('|', $record['Ai_Proportion'])) : [];

            $config[$row['id']] = $row;
        }
        $this->createConfigFile('club/grade', $config);
    }

    public function initLeagueConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['gradeId'] = (int)$record['Grade_Id'];
            $row['gradeLevel'] = $record['Grade_Level'];

            // 解析排名等级（int[]）
            $row['level'] = array_map('intval', explode('|', $record['Level']));
            $parts = explode('|', $record['Reward_Props']);
            // 解析道具奖励（int[]）
            $row['rewardProps'] = [
                'itemId' => (int)$parts[0], // 道具 ID
                'count' => (int)$parts[1], // 道具数量
            ];
            $row['rewardGrade'] = $record['Reward_Grade'];
            $row['rewardId'] = (int)$record['Reward_Id'];

            $config[$row['gradeId']][] = $row;
        }
        $this->createConfigFile('club/league', $config);
    }

    public function initLevelConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];
            $row['clubLevel'] = (int)$record['ClubLevel'];

            // 解析捐献进度值（可为空）
            $row['donate'] = $record['Donate'] !== '' ? (int)$record['Donate'] : 0;

            // 解析捐献金额（int）
            $row['donateCoin'] = (int)$record['DonateCoin'];

            // 解析人数上限（int）
            $row['member'] = (int)$record['Member'];

            // 解析奖励上限（int）
            $row['rewardLimit'] = (int)$record['RewardLimit'];

            // 解析奖励时间上限（float）
            $row['timeLimit'] = $record['TimeLimit'] !== '' ? (float)$record['TimeLimit'] : 0;

            // 解析解锁称谓（string[]）
            $row['title'] = $record['Title'] !== '' ? explode(',', $record['Title']) : [];

            // 解析特殊称谓数量（可为空）
            $row['coLeaderLimit'] = $record['CoLeaderLimit'] !== '' ? (int)$record['CoLeaderLimit'] : 0;

            $config[$row['clubLevel']] = $row;
        }
        $this->createConfigFile('club/level', $config);
    }

    public function initRequestConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];
            $row['userLevel'] = (int)$record['UserLevel'];
            $parts = explode('|', $record['RequestCoin']);
            $row['requestCoin'] = [
                'itemId' => (int)$parts[0], // 道具 ID
                'count' => (int)$parts[1], // 金币数量
            ];

            $config[$row['id']] = $row;
        }
        $this->createConfigFile('club/request', $config);
    }

    public function initSeasonConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];

            // 解析赛季开启时间（格式：YYYY|MM|DD）
            $row['seasonStart'] = $this->parseDate($record['SeasonStart']);

            // 解析赛季结束时间（格式：YYYY|MM|DD）
            $row['seasonEnd'] = $this->parseDate($record['SeasonEnd']);

            $config[$row['id']] = $row;
        }
        $this->createConfigFile('club/season', $config);
    }

    public function initWallConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['rewardType'] = (int)$record['RewardType'];
            $row['rewardTime'] = (int)$record['RewardTime'];

            $config[$row['rewardType']] = $row;
        }
        $this->createConfigFile('club/wall', $config);
    }

    public function initAiConfig($sourceFile)
    {
        $records = Utils::loadCsv($sourceFile);
        $config = array();
        foreach ($records as $record) {
            $row['id'] = (int)$record['ID'];
            $row['grade'] = $record['Grade'];
            $row['level'] = (int)$record['Level'];
            $row['pointsTime'] = (float)$record['PointsTime'];

            // 解析单次增加积分范围（int[]）
            $row['pointsScope'] = array_map('intval', explode('|', $record['PointsScope']));

            $row['member'] = (int)$record['Member'];

            $config[$row['id']] = $row;
        }
        $this->createConfigFile('club/ai', $config);
    }

}