<?php
/**
 * 机台相关业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Machines\SlotsModel\SlotsMachine;
use FF\Service\Lib\Service;
use GPBClass\Enum\RET;

class MachineBll
{
    const DATA_MACHINE = 'Machine';
    const DATA_MACHINE_ITEMS = 'MachineItems';
    const DATA_SAMPLES = 'Samples';
    const DATA_SAMPLE_REF = 'SampleRef';
    const DATA_SAMPLE_ITEMS = 'SampleItems';
    const DATA_PAYLINES = 'Paylines';
    const DATA_PAYTABLE = 'Paytable';
    const DATA_FEATURE_GAMES = 'FeatureGames';
    const DATA_REEL_ITEMS = 'ReelItems';

    private $machineBetOptions = array();

    protected $machineMocks = [];

    /**
     * 获取机台实例
     * @return SlotsMachine
     */
    public function getMachineInstance($uid, $machineId, $gameInfo = null)
    {
        if (isset($this->machineMocks[$uid])
            && $this->machineMocks[$uid]->getMachineId() == $machineId) {
            return $this->machineMocks[$uid];
        }

        $machine = $this->getMachine($machineId);
        if (!$machine) FF::throwException(RET::RET_MACHINE_NOT_EXIST);

        $machineClass = 'FF\\Machines\\' . str_replace(' ', '', $machine['className']);
        if (!class_exists($machineClass)) {
            $machineClass = 'FF\\Machines\\SlotsModel\\SlotsMachine';
        }

        $machineMock = new $machineClass($uid, $machineId, $gameInfo);

        if (Service::isRunning()) {
            $this->machineMocks[$uid] = $machineMock;
        }

        return $machineMock;
    }

    public function getConfigData($machineId, $dataType)
    {
        return Config::get("machine/machine-{$machineId}", $dataType);
    }

    public function getSourceData($machineId, $dataType)
    {
        $data = array();

        switch ($dataType) {
            case self::DATA_MACHINE:
                $data = Model::machine()->getOne($machineId);
                $data['options'] = (array)json_decode($data['options'], true);
                break;
            case self::DATA_MACHINE_ITEMS:
                $items = Model::machineItem()->getItems($machineId);
                foreach ($items as &$item) {
                    $item['options'] = $item['options'] ? json_decode($item['options'], true) : array();
                }
                $data = $items ? array_column($items, null, 'elementId') : array();
                ksort($data);
                break;
            case self::DATA_SAMPLES:
                $data = Model::sample()->getSamples($machineId);
                foreach ($data as &$sample) {
                    $sample['betLevel'] = Bll::config()->parseValue($sample['betLevel']);
                    $sample['cash'] = Bll::config()->parseValue($sample['cash']);
                    $sample['feature'] = $sample['feature'] ? explode(',', $sample['feature']) : array();
                }
                $data = array_column($data, null, 'sampleId');
                break;
            case self::DATA_SAMPLE_ITEMS:
                $list = Model::sampleItems()->getItems($machineId);
                foreach ($list as $row) {
                    $sampleId = $row['sampleId'];
                    $sampleItems = explode(',', $row['items']);
                    foreach ($sampleItems as $key => $elementId) {
                        if ($elementId === '') unset($sampleItems[$key]);
                    }
                    $sampleItems = array_values($sampleItems);
                    $sampleItems = implode(',', $sampleItems);
                    if (!isset($data[$sampleId])) $data[$sampleId] = array();
                    $data[$sampleId][$row['reel']] = $sampleItems;
                }
                break;
            case self::DATA_SAMPLE_REF:
                $list = Model::sampleRef()->getAll($machineId);
                foreach ($list as $row) {
                    $sampleId = $row['sampleId'];
                    $row['triggerOptions'] = (array)json_decode($row['triggerOptions'], true);
                    $row['itemAwardLimit'] = (array)json_decode($row['itemAwardLimit'], true);
                    if (!isset($data[$sampleId])) $data[$sampleId] = array();
                    $data[$sampleId][$row['featureId']] = $row;
                }
                break;
            case self::DATA_PAYLINES:
                $lines = Model::payline()->getAllLine($machineId);
                foreach ($lines as &$line) {
                    $line['route'] = explode(',', str_replace(' ', '', $line['route']));
                    foreach ($line['route'] as $k => $v) {
                        $line['route'][$k] = (int)$v;
                    }
                }
                $data = $lines ? array_column($lines, null, 'seq') : array();
                ksort($data);
                break;
            case self::DATA_PAYTABLE:
                $results = Model::paytable()->getAllResult($machineId);
                foreach ($results as &$result) {
                    $result['elements'] = explode(',', str_replace(' ', '', $result['elements']));
                }
                $data = $results ? array_column($results, null, 'resultId') : array();
                ksort($data);
                break;
            case self::DATA_FEATURE_GAMES:
                $features = Model::featureGame()->getAll($machineId);
                foreach ($features as &$feature) {
                    $feature['triggerLines'] = explode(',', str_replace(' ', '', $feature['triggerLines']));
                    $feature['triggerOptions'] = (array)json_decode($feature['triggerOptions'], true);
                    $feature['coinsAward'] = Bll::config()->parseValue($feature['coinsAward']);
                    $feature['freespinAward'] = Bll::config()->parseValue($feature['freespinAward']);
                    $feature['itemAward'] = Bll::config()->parseValue($feature['itemAward']);
                    $feature['itemAwardLimit'] = (array)json_decode($feature['itemAwardLimit'], true);
                }
                $data = $features ? array_column($features, null, 'featureId') : array();
                ksort($data);
                break;
            default:
                break;
        }

        return $data;
    }

    public function getMachine($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_MACHINE);
    }

    public function getMachineItems($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_MACHINE_ITEMS);
    }

    public function getSamples($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_SAMPLES);
    }

    public function getPaylines($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_PAYLINES);
    }

    public function getPaytable($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_PAYTABLE);
    }

    public function getFeatureGames($machineId)
    {
        return $this->getConfigData($machineId, self::DATA_FEATURE_GAMES);
    }

    public function getMachineCollect($machineId)
    {
        if (!$machineId) return array();

        $nodes = Config::get('machine/machine-collect', "{$machineId}", false);

        return $nodes ?: array();
    }

    /**
     * 获取机台下注配置MSG_SLOTS_BETTING
     */
    public function getMachineBet($machineId)
    {
        $machineBetCfg = Config::get('machine/common-bet');

        // 处理 betOptions 数值
        $machineBetCfg['betOptions'] = array();
        if (!empty($this->machineBetOptions)) {
            $machineBetCfg['betOptions'] = $this->machineBetOptions;
        } else {
            // betOptions == betUnlock 所有 totalBet 值，baseBet 固定为 100
            array_walk_recursive($machineBetCfg['betUnlock'], function ($totalBet) use (&$machineBetCfg) {
                $machineBetCfg['betOptions'][$totalBet / 100] = $totalBet;
            });
            $this->machineBetOptions = $machineBetCfg['betOptions'];
        }

        return $machineBetCfg;
    }

    /**
     * 获取机台下注选项集合
     */
    public function getBetOptions($machineId)
    {
        $machineBet = $this->getMachineBet($machineId);

        return $machineBet ? $machineBet['betOptions'] : array();
    }

    public function getAllMachines()
    {
        $machines = Config::get('machines');

        return $machines;
    }

    public function getMachineById($machineId)
    {
        $machine = Config::get('machines', $machineId);

        return $machine;
    }
}
