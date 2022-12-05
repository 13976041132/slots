<?php
/**
 * 游戏行为相关
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class GameController extends BaseController
{
    /**
     * 查看下注记录
     */
    public function betLog()
    {
        $uid = (int)$this->getParam('uid', false);
        $machineId = $this->getParam('machineId', false);
        $isFreeSpin = $this->getParam('isFreeSpin', false);
        $inFeature = $this->getParam('inFeature', false);
        $triggerFeature = $this->getParam('triggerFeature', false);
        $startDate = $this->getParam('startDate', false);
        $endDate = $this->getParam('endDate', false);
        $table = $this->getParam('table', false);
        $page = (int)$this->getParam('page', false, 1);
        $limit = (int)$this->getParam('limit', false, 15);
        $featureGames = [];

        $where = array();
        if ($uid) $where['uid'] = $uid;
        if ($machineId) $where['machineId'] = $machineId;
        if ($startDate && $endDate) {
            $where['time'] = array('between', [$startDate, $endDate]);
        } elseif ($startDate) {
            $where['time'] = array('>', $startDate);
        } elseif ($endDate) {
            $where['time'] = array('<', $endDate);
        }

        if ($machineId) {
            $featureGames[$machineId] = Config::get("machine/machine-{$machineId}", 'FeatureGames', false) ?: [];
            if ($isFreeSpin !== '') $where['isFreeSpin'] = $isFreeSpin ? 1 : 0;
            if ($featureGames[$machineId]) {
                if ($inFeature && isset($featureGames[$machineId][$inFeature])) $where['feature'] = (string)$inFeature;
                if ($triggerFeature && isset($featureGames[$machineId][$triggerFeature])) $where['features'] = array('like', '%' . $triggerFeature . '%');
            }
        }

        $orderBy = array('microtime' => 'desc');
        if (substr($table, 0, 4) === 'test') {
            $orderBy = array('uid' => 'asc', 'microtime' => 'asc');
        }
        $data = Model::betLog($table)->getPageList($page, $limit, $where, null, $orderBy);

        foreach ($data['list'] as &$row) {
            $_machineId = $row['machineId'];
            if (!isset($featureGames[$_machineId])) {
                $featureGames[$_machineId] = Config::get("machine/machine-{$_machineId}", 'FeatureGames', false) ?: [];
            }
            $row['featureSteps'] = json_decode($row['featureSteps'] ?: '{}', true);
            $row['features'] = explode(',', $row['features']);
        }

        $data['features'] = array_column($featureGames[$machineId] ?? [], 'featureName', 'featureId');
        $data['machines'] = Bll::machine()->getAllMachines();

        //历史表
        $data['tables'] = Model::betLog()->getHistoryTables();

        $data['featureGames'] = $featureGames;

        $this->display('betLog.html', $data);
    }

    /**
     * 获取机台feature列表
     */
    public function getFeatures()
    {
        $machineId = $this->getParam('machineId');
        $featureGames = Config::get("machine/machine-{$machineId}", 'FeatureGames', false) ?: [];

        return array('features' => array_column($featureGames, 'featureName', 'featureId'));
    }

    /**
     * 查看下注结果
     */
    public function betResult()
    {
        $id = (int)$this->getParam('id');
        $table = $this->getParam('table', false);
        $action = $this->getParam('action', false, '');

        $betLog = Model::betLog($table)->getBetLogByAction($id, $action, !!$table);
        if (!$betLog) FF::throwException(Code::PARAMS_INVALID);

        $machineId = $betLog['machineId'];
        $featureId = $betLog['feature'];

        $machine = Bll::machine()->getMachineById($machineId);
        $featureGames = Config::get("machine/machine-{$machineId}", 'FeatureGames', false) ?: [];
        $betLog['features'] = $betLog['features'] ? explode(',', $betLog['features']) : array();

        //是否是子机台
        if ($featureId && $featureGames[$featureId]['subMachine']) {
            $machineId = $featureGames[$featureId]['subMachine'];
        }

        $stickyElements = json_decode($betLog['stickyElements'] ?: '{}', true);
        $steps = json_decode($betLog['steps'], true);
        $extra = json_decode($betLog['extra'], true);
        $featureSteps = json_decode($betLog['featureSteps'], true);
        $maxRow = 0;
        foreach ($steps as &$stepInfo) {
            $elements = array();
            $elementsPrize = array();
            if ($stepInfo['step'] == 1) {
                foreach ($stickyElements as $col => $colElements) {
                    foreach ($colElements as $row => $elementId) {
                        $elementsPrize[$row][$col] = array(
                            'col' => $col,
                            'row' => $row,
                            'elementId' => $elementId
                        );
                    }
                }
            }
            foreach ($stepInfo['prizes']['elements']??[] as $element) {
                $col = $element['col'];
                $row = $element['row'];
                $elementsPrize[$row][$col] = $element;
            }
            foreach ($stepInfo['elements'] as $element) {
                $col = $element['col'];
                $row = $element['row'];
                $elements[$row][$col] = $element;
            }
            krsort($elements);
            foreach ($elements as $row => &$_elements) {
                ksort($_elements);
            }

            $maxRow = max($maxRow, key($elements));
            $stepInfo['elements'] = $elements;
            $stepInfo['maxRow'] = &$maxRow;
            $stepInfo['prizes']['elements'] = $elementsPrize;
        }

        $data['log'] = $betLog;
        $data['steps'] = $steps;
        $data['extra'] = $extra;
        $data['featureSteps'] = $featureSteps;
        $data['machine'] = Bll::machine()->getMachineById($machineId);
        $data['machineItems'] = Config::get("machine/machine-{$machineId}", 'MachineItems', false) ?: [];
        $data['featureGames'] = $featureGames;
        $data['betLogId'] = $betLog['id'];
        $data['isLast'] = $action && $id == $betLog['id'];
        $data['iconUrl'] = RES_URL . '/machine-icons/' . $machine['name'];

        $this->display('betResult.html', $data);
    }

    /**
     * 查看Feature结果
     */
    public function featureSteps()
    {
        $betId = (int)$this->getParam('betId');
        $featureId = $this->getParam('featureId');
        $table = $this->getParam('table', false);
        $action = $this->getParam('action', false, '');

        $betLog = Model::betLog($table)->getBetLogByAction($betId, $action, !!$table);
        if (!$betLog) FF::throwException(Code::PARAMS_INVALID);

        $data['totalBet'] = $betLog['totalBet'];
        $data['featureId'] = $featureId;
        $machineId = $betLog['machineId'];
        $featureGames = Bll::machine()->getFeatureGames($machineId);
        $data['featureGames'] = $featureGames;

        $data['machine'] = Bll::machine()->getMachineById($machineId);
        $data['machineItems'] = Bll::machine()->getMachineItems($machineId);
        $data['machinesItems'] = [];

        $featureSteps = json_decode($betLog['featureSteps'], true);
        $data['featureSteps'] = $featureSteps[$featureId]['steps'] ?? array();
        $data['featureTotalWin'] = $featureSteps[$featureId]['featureWin'] ?: 0;
        $wheelIds = [];

        foreach ($data['featureSteps'] as $key => &$item) {
            if (isset($item['prizes'][ITEM_COINS])) {
                $item['prizes']['coins'] = $item['prizes'][ITEM_COINS] ?: 0;
            } elseif (isset($item['prizes'][ITEM_JACKPOT])) {
                $item['prizes']['coins'] = $item['prizes'][ITEM_JACKPOT]['coins'] ?: 0;
            }
            if (!empty($item['wheelId'])) {
                $wheelIds[] = $item['wheelId'];
            }

        }

        $wheelsInfo = [];
        $wheelItems = [];
        foreach ($wheelIds as $wheelId) {
            $wheelsInfo[$wheelId] = Bll::wheel()->getWheelInfo($wheelId);
            $wheelItems[$wheelId] = Bll::wheel()->getWheelItems($wheelId);
        }

        $data['wheelsInfo'] = $wheelsInfo;
        $data['wheelItems'] = $wheelItems;

        $this->display('featureSteps.html', $data);
    }

}