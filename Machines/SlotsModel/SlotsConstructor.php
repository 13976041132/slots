<?php
/**
 * 老虎机构造器
 */

namespace FF\Machines\SlotsModel;

use FF\Bll\MachineBll;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Core\FF;
use FF\Library\Utils\Utils;

abstract class SlotsConstructor
{
    protected $uid;
    protected $instanceId;
    protected $machineId;
    protected $machineName;
    protected $machine;
    protected $machineItems;
    protected $sheetGroup;
    protected $samples;
    protected $sampleItems;
    protected $sampleRef;
    protected $paylines;
    protected $paytable;
    protected $paytableGeneral;
    protected $paytableFreeSpin;
    protected $paytableGroups;
    protected $machineCollect;
    protected $featureGames;
    protected $featureGamesBak;
    protected $jackpots;

    protected $frameElements = [];
    protected $wildElements = [];
    protected $scatterElements = [];
    protected $bonusElements = [];
    protected $commonElements = [];

    protected $gameInfo = array();
    protected $gameInfoBak = array();
    protected $freespinInfo = array();

    protected $userInfo = array();
    protected $analysisInfo = array();
    protected $analysisInfoBak = array();
    protected $balance = 0;

    protected $betId;
    protected $betContext;
    protected $betOptionList;
    protected $betOptions;
    protected $ultraBetOptionList;
    protected $ultraBetOptions;
    protected $runOptions = array();
    protected $step;

    public $isVirtualMode = false;
    public $isSpinning = false;
    public $hasEliminate = false;

    public $slotNum = 1; // 每次 spin 的 slot 数量

    //获取本次spin某一步的元素列表
    abstract public function getStepElements($step = null);

    //判断feature是否是FreeGame
    abstract public function isFreeGame($featureId);

    //判断feature列表中是否有FreeGame
    abstract public function hasFreeGame($features);

    //判断当前是否在FreeGame中
    abstract public function isInFreeGame();

    public function __construct($uid, $machineId, $gameInfo = array())
    {
        $this->uid = $uid;
        $this->machineId = $machineId;
        $this->gameInfo = $gameInfo;
        $this->isVirtualMode = defined('TEST_ID');
        $this->userInfo = Bll::user()->getOne($uid);
        $this->balance = $this->userInfo['coins'];
        $this->initMachine();
        $this->initPlayer();
        $this->initBetContext();

        $instanceId = "{$uid}:{$machineId}:" . Utils::getRandChars(8);

        $this->instanceId = $instanceId;
    }

    public static function getInstance($uid, $machineId, $gameInfo = null)
    {
        $machineObj = Bll::machine()->getMachineInstance($uid, $machineId, $gameInfo);

        return $machineObj;
    }

    public function getInstanceId()
    {
        return $this->instanceId;
    }

    public function clearBuffer()
    {
        $this->featureGames = $this->featureGamesBak;
    }

    /**
     * 初始化机台配置
     */
    protected function initMachine()
    {
        $machineId = $this->machineId;

        $config = Bll::machine()->getConfigData($machineId, '');

        $this->machine = $config[MachineBll::DATA_MACHINE];
        $this->machineName = $this->machine['name'];

        //合并下注控制信息到机台信息
        $this->initMachineBets();

        $this->machineItems = $config[MachineBll::DATA_MACHINE_ITEMS];
        $this->paylines = $config[MachineBll::DATA_PAYLINES];
        $this->paytable = $config[MachineBll::DATA_PAYTABLE];
        $this->samples = $config[MachineBll::DATA_SAMPLES];

        $this->sampleItems = $config[MachineBll::DATA_SAMPLE_ITEMS];
        $this->sampleRef = $config[MachineBll::DATA_SAMPLE_REF];
        $this->featureGames = $config[MachineBll::DATA_FEATURE_GAMES];
        $this->featureGamesBak = $config[MachineBll::DATA_FEATURE_GAMES];
        $this->machineCollect = Bll::machine()->getMachineCollect($machineId);
        $this->jackpots = Bll::jackpot()->getJackpots($machineId);

        $this->elementsClassify();
        $this->paytableClassify();
        $this->initSheetGroup();
    }

    /**
     * 初始机台 Bet 列表
     */
    private function initMachineBets()
    {
        $uid = $this->uid;
        $machineId = $this->machineId;

        //合并下注控制信息到机台信息
        $machineBet = Bll::machine()->getMachineBet($machineId);
        $this->machine = array_merge($this->machine, $machineBet);

        // UnlockBets
        $this->betOptionList = Bll::machineBet()->getUnlockBetOptionList($uid, $machineId, $this->userInfo['level']);

        // UltraBets，测试模式不使用
        $this->ultraBetOptionList = $this->isVirtualMode ? [] : Bll::ultraBet()->getUltraBetList($uid);

        $this->ultraBetOptions = array_column($this->ultraBetOptionList, 'totalBet', 'betMultiple');
        $this->betOptions = array_column($this->betOptionList, 'totalBet', 'betMultiple');
        // 合并UltraBet
        $this->betOptions += $this->ultraBetOptions;

        ksort($this->betOptions);
    }

    /**
     * 初始化玩家信息
     */
    private function initPlayer()
    {
        //初始化玩家分析数据
        if (!$this->analysisInfo) {
            if ($this->isVirtualMode) {
                //在测试模式中,取用户数据作为分析数据
                $this->analysisInfo = Bll::analysis()->initAnalysisInInTestMode($this->uid);
                if (defined('IS_NOVICE') && !IS_NOVICE) {
                    $this->analysisInfo['noviceEnded'] = 1;
                }
            } else {
                $this->analysisInfo = Bll::analysis()->getAnalysisInfo($this->uid);
            }
        }
        $this->analysisInfoBak = $this->analysisInfo;

        //初始化玩家机台数据
        if (!$this->gameInfo) {
            if ($this->isVirtualMode) {
                $this->gameInfo = Bll::game()->initVirtualInfo($this->uid, $this->machineId);
            } else {
                $this->gameInfo = Bll::game()->getGameInfo($this->uid, $this->machineId);
            }
        }
        $this->gameInfoBak = $this->gameInfo;

        //初始化freespin信息
        if (!$this->freespinInfo) {
            $features = [];
            $currFeature = $this->gameInfo['featureId'];
            $bakFeatures = $this->gameInfo['bakFeatures'];
            if ($bakFeatures) {
                $features = array_column($bakFeatures, 'featureId');
            }
            if ($currFeature) {
                $features[] = $currFeature;
            }
            if ($features && $this->hasFreeGame($features)) {
                $this->freespinInfo = Bll::freespin()->getFreespinInfo($this->uid, $this->machineId);
            } else {
                $this->freespinInfo = Bll::freespin()->getInitInfo(0);
            }
        }
    }

    /**
     * 初始化下注上下文
     */
    public function initBetContext()
    {
        //当前下注比
        if (defined('TEST_BET_RATIO') && TEST_BET_RATIO) {
            $betRatio = TEST_BET_RATIO;
        } else {
            $betRatio = floor($this->balance / $this->gameInfo['totalBet']);
        }

        $isFreeSpin = $this->freespinInfo['totalTimes'] > 0;
        $isLastFreeSpin = $isFreeSpin && $this->freespinInfo['totalTimes'] == $this->freespinInfo['spinTimes'];

        $this->betContext = array();
        $this->betContext['betMultiple'] = $this->gameInfo['betMultiple'];
        $this->betContext['totalBet'] = $this->gameInfo['totalBet'];
        $this->betContext['resumeBet'] = $this->gameInfo['resumeBet'];
        $this->betContext['totalWin'] = $this->gameInfo['totalWin'];
        $this->betContext['betRatio'] = $betRatio;
        $this->betContext['feature'] = $this->gameInfo['featureId'];
        $this->betContext['featureNo'] = $this->gameInfo['featureNo'];
        $this->betContext['isFreeSpin'] = $isFreeSpin;
        $this->betContext['isLastFreeSpin'] = $isLastFreeSpin;
        $this->betContext['preFeatures'] = [];
    }

    /**
     * 对机台元素进行分类
     */
    protected function elementsClassify()
    {
        $this->wildElements = array();
        $this->scatterElements = array();
        $this->bonusElements = array();
        $this->commonElements = array();

        foreach ($this->machineItems as $elementId => $item) {
            if ($this->isWildElement($elementId)) {
                $this->wildElements[] = $elementId;
            } elseif ($this->isScatterElement($elementId)) {
                $this->scatterElements[] = $elementId;
            } elseif ($this->isBonusElement($elementId)) {
                $this->bonusElements[] = $elementId;
            } elseif ($this->getElementType($elementId) === '01') {
                $this->commonElements[] = $elementId;
            } elseif ($this->isFrameElement($elementId)) {
                $this->frameElements[] = $elementId;
            }
        }
    }

    /**
     * 对paytable进行分类
     */
    protected function paytableClassify()
    {
        $this->paytableGeneral = array();
        $this->paytableFreeSpin = array();
        $this->paytableGroups = array();

        foreach ($this->paytable as $resultId => $result) {
            if ($result['freeSpinOnly'] == 'Y') {
                $this->paytableFreeSpin[$resultId] = $result;
            } else {
                $this->paytableGeneral[$resultId] = $result;
            }
        }
    }

    /**
     * 获取成员变量的值
     */
    public function getMember($field)
    {
        return $this->$field;
    }

    public function getMachineId()
    {
        return $this->machineId;
    }

    public function getMachineName()
    {
        return $this->machineName ?: $this->machine['name'];
    }

    public function getMachineOptions($key = '')
    {
        if ($key) {
            return $this->machine['options'][$key] ?? null;
        } else {
            return $this->machine['options'];
        }
    }

    public function getBetOptionList()
    {
        return $this->betOptionList;
    }

    public function getBetOptions()
    {
        return $this->machine['betOptions'];
    }

    public function getBetRaiseOpt()
    {
        return $this->machine['betRaise'];
    }

    public function getBetMultiple()
    {
        return $this->betContext['betMultiple'];
    }

    public function getTotalBet()
    {
        return $this->betContext['totalBet'];
    }

    public function getTotalWin()
    {
        return $this->betContext['totalWin'];
    }

    public function getBetContext($key = null)
    {
        return $key ? $this->betContext[$key] : $this->betContext;
    }

    public function getUserInfo($key = null)
    {
        return $key ? $this->userInfo[$key] : $this->userInfo;
    }

    public function getGameInfo($key = null)
    {
        return $key ? $this->gameInfo[$key] : $this->gameInfo;
    }

    public function getAnalysisInfo($key = null)
    {
        return $key ? $this->analysisInfo[$key] : $this->analysisInfo;
    }

    public function getFreespinInfo($key = null)
    {
        return $key ? $this->freespinInfo[$key] : $this->freespinInfo;
    }

    public function getBalance()
    {
        return $this->balance;
    }

    /**
     * 获取当前下注挡位所属分组
     */
    public function getBetGroup()
    {
        $betIndex = $this->getTotalBetIndex() + 1;

        foreach ($this->machine['betGroup'] as $betGroup => $betRect) {
            if ($betIndex >= $betRect[0] && (!$betRect[1] || $betIndex < $betRect[1])) {
                return (int)$betGroup;
            }
        }

        return 1;
    }

    /**
     * 获取当前TotalBet档位
     */
    public function getTotalBetIndex($totalBet = null)
    {
        $totalBet = $totalBet ?: $this->betContext['totalBet'];
        $totalBets = array_values($this->betOptions);

        $index = 0;
        foreach ($totalBets as $k => $_totalBet) {
            if ($totalBet >= $_totalBet) {
                $index = $k;
            } else {
                break;
            }
        }

        return $index;
    }

    /**
     * 根据档位查找对应的TotalBet值
     */
    public function getTotalBetByIndex($index)
    {
        $totalBets = array_values($this->betOptions);

        if (!$index) {
            return $totalBets[0];
        } elseif (!isset($totalBets[$index - 1])) {
            return array_pop($totalBets);
        } else {
            return $totalBets[$index - 1];
        }
    }

    /**
     * 初始化转轴格子
     */
    public function initSheetGroup()
    {
        $this->sheetGroup = array();

        for ($cellCol = 1; $cellCol <= $this->machine['cols']; $cellCol++) {
            for ($cellRow = 1; $cellRow <= $this->machine['rows']; $cellRow++) {
                $this->sheetGroup[$cellCol][$cellRow] = array(
                    'col' => $cellCol, 'row' => $cellRow,
                );
            }
        }
    }

    /**
     * 获取当前转轴格子
     */
    public function getSheetGroup($featureId = '')
    {
        return $this->sheetGroup;
    }

    /**
     * 启用转轴格子
     */
    public function enableSheets($col = null, $row = null)
    {
        if ($col && $row) {
            $this->sheetGroup[$col][$row] = array(
                'col' => $col, 'row' => $row,
            );
        } elseif ($col) {
            for ($row = 1; $row <= $this->machine['rows']; $row++) {
                $this->sheetGroup[$col][$row] = array(
                    'col' => $col, 'row' => $row,
                );
            }
        } elseif ($row) {
            for ($col = 1; $col <= $this->machine['cols']; $col++) {
                $this->sheetGroup[$col][$row] = array(
                    'col' => $col, 'row' => $row,
                );
            }
        }
    }

    /**
     * 禁用转轴格子
     */
    public function disableSheets($col = null, $row = null)
    {
        if ($col && $row) {
            unset($this->sheetGroup[$col][$row]);
        } elseif ($col) {
            unset($this->sheetGroup[$col]);
        } elseif ($row) {
            for ($col = 1; $col <= $this->machine['cols']; $col++) {
                unset($this->sheetGroup[$col][$row]);
            }
        }
    }

    /**
     * paytable按中奖元素及个数分组
     */
    public function getPaytableGroup($isInFreeGame = false)
    {
        $tag = 0;
        if ($isInFreeGame && $this->paytableFreeSpin) {
            $tag = 1;
        }

        if (isset($this->paytableGroups[$tag])) {
            return $this->paytableGroups[$tag];
        }

        if ($isInFreeGame && $this->paytableFreeSpin) {
            $paytable = $this->paytableFreeSpin;
        } else {
            $paytable = $this->paytableGeneral;
        }

        $paytableGroup = array();
        foreach ($paytable as $resultId => $v) {
            $elementId = $v['elements'][0];
            $hitNum = count(array_filter($v['elements']));
            $paytableGroup[$elementId][$hitNum] = $resultId;
        }

        $this->paytableGroups[$tag] = $paytableGroup;

        return $paytableGroup;
    }

    /**
     * 根据totalBet查找betMultiple
     */
    public function findBetMultiple($totalBet)
    {
        $betMultiples = array_flip($this->betOptions);

        return $betMultiples[$totalBet] ?? 0;
    }

    /**
     * 设置用户在本机台的下注倍数
     */
    public function setTotalBet($totalBet, $resumeBet = 0)
    {
        if ($totalBet <= 0) return;

        $betOptions = array_flip($this->betOptions);

        if (isset($betOptions[$totalBet])) {
            $betMultiple = $betOptions[$totalBet];
        } else {
            $minTotalBet = array_keys($betOptions)[0];
            $minBetMultiple = $betOptions[$minTotalBet];
            $betMultiple = floor($totalBet * $minBetMultiple / $minTotalBet);
        }

        if (($this->isSpinning && !$this->isFreeSpin()) || $this->checkTotalBet()) {
            $this->betContext['betMultiple'] = $betMultiple;
            $this->betContext['totalBet'] = $totalBet;
        }

        $this->updateGameInfo(array(
            'betMultiple' => $betMultiple,
            'totalBet' => $totalBet,
            'resumeBet' => $resumeBet
        ), true);
    }

    /**
     * 更新TotalWin
     */
    public function setTotalWin($totalWin)
    {
        $this->updateGameInfo(array('totalWin' => $totalWin));

        if (!$this->isSpinning) {
            $this->betContext['totalWin'] = $totalWin;
        }
    }

    /**
     * 更新连续下注次数
     */
    public function updateBetTimes($totalBet, $lastBet)
    {
        if ($this->isVirtualMode) return;

        if ($totalBet == $lastBet) {
            $betTimes = $this->gameInfo['betTimes'] + 1;
            if ($betTimes % 10 == 0) {
                Model::userBetData()->updateBetTimes($this->uid, today(), $this->machineId, $totalBet, 10);
            }
        } else {
            $times = $this->gameInfo['betTimes'] % 10;
            if ($times) {
                Model::userBetData()->updateBetTimes($this->uid, today(), $this->machineId, $lastBet, $times);
            }
            $betTimes = 1;
        }

        $this->updateGameInfo(array('betTimes' => $betTimes));
    }

    /**
     * 更新下注均值
     */
    public function updateAvgBet($totalBet)
    {
        if ($this->gameInfo['avgBet']) {
            $spinTimes = $this->gameInfo['spinTimes'];
            $betSummary = $spinTimes * $this->gameInfo['avgBet'];
            $avgBet = floor(($betSummary + $totalBet) / ($spinTimes + 1));
        } else {
            $avgBet = $totalBet;
        }

        $this->gameInfo['avgBet'] = $avgBet;
    }

    /**
     * 更新用户游戏信息
     */
    public function updateGameInfo($data, $save = false)
    {
        foreach ($data as $key => $value) {
            $this->gameInfo[$key] = $value;
        }

        if ($save && !$this->isVirtualMode) {
            Bll::game()->updateGameInfo($this->uid, $this->machineId, $data);
        }
    }

    /**
     * 保存玩家游戏信息
     * 只对有变化的字段进行更新
     */
    public function saveGameInfo($sync = false)
    {
        if ($this->isVirtualMode) return;

        $changed = $this->getChangedData($this->gameInfo, $this->gameInfoBak);

        if ($changed) {
            Bll::game()->updateGameInfo($this->uid, $this->machineId, $changed, $sync);
            $this->gameInfoBak = $this->gameInfo;
        }
    }

    /**
     * 保存玩家游戏分析数据
     * 只对有变化的字段进行更新
     */
    public function saveAnalysisInfo()
    {
        if ($this->isVirtualMode) return;

        $changed = $this->getChangedData($this->analysisInfo, $this->analysisInfoBak);

        if ($changed) {
            Bll::analysis()->updateAnalysisInfo($this->uid, $this->analysisInfo);
            Bll::analysis()->updateMachineSpinTimes($this->uid,$this->machineId, $this->gameInfo['spinTimes']);

            $this->analysisInfoBak = $this->analysisInfo;
        }
    }

    public function getChangedData($data, $dataBak)
    {
        $changed = array();

        foreach ($data as $key => $value) {
            $valueBak = $dataBak[$key];
            if (is_array($value)) {
                $value = $value ? json_encode($value) : '';
                $valueBak = $valueBak ? json_encode($valueBak) : '';
            }
            if ($value != $valueBak) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * 生成元素上的附加值
     */
    public function getElementsValue($elements, $features = array())
    {
        return array();
    }

    public function isElementsList($elements)
    {
        return isset($elements[0]);
    }

    public function elementsToList($elements)
    {
        if (!$elements) return array();

        //已经是List结构
        if (isset($elements[0])) return $elements;

        $list = array();
        ksort($elements, SORT_NUMERIC);
        foreach ($elements as $col => $_elements) {
            ksort($_elements, SORT_NUMERIC);
            foreach ($_elements as $row => $elementId) {
                $list[] = array(
                    'elementId' => $elementId,
                    'col' => (int)$col,
                    'row' => (int)$row
                );
            }
        }

        return $list;
    }

    public function elementsToRow($elements)
    {
        $rowElements = array();

        foreach ($elements as $col => $_elements) {
            foreach ($_elements as $row => $elementId) {
                if (!isset($rowElements[$row])) $rowElements[$row] = array();
                $rowElements[$row][$col] = $elementId;
            }
        }

        return $rowElements;
    }

    public function elementsListToPoint($elementsList, $rowFirst = false)
    {
        $elements = array();

        foreach ($elementsList as $v) {
            $col = $v['col'];
            $row = $v['row'];
            if ($rowFirst) {
                $elements[$row][$col] = $v['elementId'];
            } else {
                $elements[$col][$row] = $v['elementId'];
            }
        }

        return $elements;
    }

    public function elementIdsToShort(&$elementIds)
    {
        foreach ($elementIds as &$elementId) {
            $elementId = (int)substr($elementId, -4);
        }
    }

    public function elementsMerge($elements, $elements2)
    {
        foreach ($elements2 as $col => $_elements) {
            if (!isset($elements[$col])) $elements[$col] = array();
            foreach ($_elements as $row => $elementId) {
                $elements[$col][$row] = $elementId;
            }
        }

        return $this->elementsSort($elements);
    }

    public function elementsValueMerge($values1, $values2, $join = true)
    {
        foreach ($values2 as $col => $_values) {
            foreach ($_values as $row => $value) {
                if (isset($values1[$col][$row]) && $join) {
                    $value = $values1[$col][$row] . ',' . $value;
                }
                $values1[$col][$row] = $value;
            }
        }

        return $values1;
    }

    public function elementsSort($elements)
    {
        ksort($elements, SORT_NUMERIC);
        foreach ($elements as $col => $_elements) {
            ksort($_elements, SORT_NUMERIC);
            $elements[$col] = $_elements;
        }

        return $elements;
    }

    public function elementsCount($elements)
    {
        $counts = array();

        foreach ($elements as $col => $_elements) {
            foreach ($_elements as $row => $elementId) {
                $counts[$elementId]++;
            }
        }

        return $counts;
    }

    public function getElementName($elementId)
    {
        if (!$elementId) return '';

        return $this->machineItems[$elementId]['iconDescription'];
    }

    public function getElementType($elementId)
    {
        if (!$elementId) return '';

        return $this->machineItems[$elementId]['iconType'];
    }

    public function getElementOptions($elementId)
    {
        if (!$elementId) return array();

        return $this->machineItems[$elementId]['options'];
    }

    public function getElementByName($name)
    {
        $elements = array_column($this->machineItems, 'elementId', 'iconDescription');

        return $elements[$name];
    }

    public function isWildElement($elementId)
    {
        return $this->machineItems[$elementId]['iconType'] == '99';
    }

    public function isFrameElement($elementId)
    {
        return $this->machineItems[$elementId]['iconType'] == '94';
    }

    public function isScatterElement($elementId)
    {
        return $this->machineItems[$elementId]['iconType'] == '98';
    }

    public function isBonusElement($elementId)
    {
        return $this->machineItems[$elementId]['iconType'] == '97';
    }

    public function isBombElement($elementId)
    {
        return $this->machineItems[$elementId]['iconType'] == '95';
    }

    public function isFreeSpin()
    {
        return $this->betContext['isFreeSpin'];
    }

    public function checkTotalBet()
    {
        // 默认不修改 betContext 的 totalbet 值
        return false;
    }

    public function getBonusElements()
    {
        return $this->bonusElements;
    }

    public function getCols()
    {
        return $this->machine['cols'];
    }

    /**
     * 更新 BetId 下注ID
     */
    public function renewBetId()
    {
        $this->betId = $this->generateBetId();

        return $this->betId;
    }

    public function generateBetId()
    {
        return date('YmdHis') . ':' . $this->uid . ':' . Utils::getRandChars(8);
    }

    /**
     * 获取 BetId
     */
    public function getBetId()
    {
        return $this->betId;
    }

    protected function getSheets()
    {
        $sheetGroup = $this->getSheetGroup();
        $sheets = [];
        foreach ($sheetGroup as $col => $_sheets) {
            foreach ($_sheets as $_sheet) {
                $sheets[] = $_sheet;
            }
        }

        return $sheets;
    }

    public function getUltraBetOptionList()
    {
        return $this->ultraBetOptionList;
    }

    public function getAllBetOptionList()
    {
        $betList = [];

        foreach ($this->betOptions as $betMultiple => $totalBet) {
            $betList[] = array(
                'betMultiple' => $betMultiple,
                'totalBet' => $totalBet,
            );
        }

        return $betList;
    }

    public function calcBetPercent($bet)
    {
        $count = count($this->betOptions);
        $bets = array_values($this->betOptions);
        $index = (int)array_search($bet, $bets) + 1;

        return ceil($index / $count * 100);
    }

}