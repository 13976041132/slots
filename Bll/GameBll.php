<?php
/**
 * 游戏业务逻辑
 */

namespace FF\Bll;

use FF\App\GameMain\Model\Main\GameInfoModel;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class GameBll extends DBCacheBll
{
    protected $fields = array(
        'uid' => ['int', NULL],
        'machineId' => ['int', NULL],
        'betId' => ['string', ''],
        'betMultiple' => ['int', 1],
        'totalBet' => ['int', 0],
        'betTimes' => ['int', 0],
        'defaultBet' => ['int', 0],
        'suggestBet' => ['int', 0],
        'resumeBet' => ['int', 0],
        'avgBet' => ['int', 0],
        'totalWin' => ['int', 0],
        'coinsWin' => ['int', 0],
        'spinTimes' => ['int', 0],
        'sampleGroup' => ['string', ''],
        'betSummary' => ['int', 0],
        'sampleCount' => ['int', 0],
        'jackpotAddition' => ['string', ''],
        'jackpotProgress' => ['int', 0],
        'collectNode' => ['int', 0],
        'collectTarget' => ['int', 0],
        'collectProgress' => ['int', 0],
        'collectSpinTimes' => ['int', 0],
        'collectBetSummary' => ['int', 0],
        'collectAvgBet' => ['int', 0],
        'collectValue' => ['int', 0],
        'featureId' => ['string', ''],
        'featureNo' => ['string', ''],
        'activated' => ['int', 0],
        'featureDetail' => ['string', ''],
        'bakFeatures' => ['string', ''],
        'stacks' => ['string', ''],
        'featureTimes' => ['string', ''],
        'bonusCredit' => ['int', 0],
        'suggestBetIntervene' => ['string', ''],
        'enterTime' => ['string', NULL],
        'enterBalance' => ['int', 0],
        'gameExtra' => ['string', ''],
        'enterCost' => ['int', 0],
        'enterWin' => ['int', 0],
        'enterSpinTimes' => ['int', 0],
        'lastSpinElements' => ['string', '']
    );

    protected $droppedFields = array('extra');

    /**
     * @return GameInfoModel
     */
    function model($uid)
    {
        return Model::gameInfo();
    }

    function getCacheKey($uid, $wheres)
    {
        return Keys::gameInfo($uid, $wheres['machineId']);
    }

    protected function redis()
    {
        return Dao::redis('game');
    }

    /**
     * 获取当前玩家在玩机台
     */
    public function getPlayingMachineId($uid)
    {
        $machineId = Bll::session()->get('machineId');

        return $machineId;
    }

    /**
     * 初始化机台下注额
     */
    public function initMachineBet($uid, $machineId)
    {
        $betOptions = Bll::machineBet()->getUnlockedBets($uid, $machineId);
        if (!$betOptions) {
            FF::throwException(Code::FAILED);
        }
        $betMultiple = (int)array_keys($betOptions)[0];
        $totalBet = $betOptions[$betMultiple];

        return [$betMultiple, $totalBet];
    }

    /**
     * 初始化虚拟用户的游戏信息
     */
    public function initVirtualInfo($uid, $machineId)
    {
        $gameInfo = array();
        list($betMultiple, $totalBet) = $this->initMachineBet($uid, $machineId);

        $gameInfo['uid'] = $uid;
        $gameInfo['machineId'] = $machineId;
        $gameInfo['betMultiple'] = $betMultiple;
        $gameInfo['totalBet'] = $totalBet;

        foreach ($this->fields as $field => $fieldCfg) {
            if (!isset($gameInfo[$field])) {
                $gameInfo[$field] = $fieldCfg[1];
            }
        }

        $this->checkGameInfo($gameInfo);

        return $gameInfo;
    }

    /**
     * 用户数据初始化入库
     */
    public function initDataInDB($uid, $data)
    {
        $machineId = $data['machineId'];

        list($betMultiple, $totalBet) = $this->initMachineBet($uid, $machineId);

        $this->model($uid)->init($uid, $machineId, $betMultiple, $totalBet);
    }

    /**
     * 获取玩家机台上游戏信息
     */
    public function getGameInfo($uid, $machineId, $fields = '*')
    {
        $wheres = array('machineId' => $machineId);
        $gameInfo = $this->getCacheData($uid, $fields, $wheres);
        if (!$gameInfo) {
            FF::throwException(Code::SYSTEM_BUSY);
        }

        $this->checkGameInfo($gameInfo);

        return $gameInfo;
    }

    /**
     * 校正游戏信息
     */
    public function checkGameInfo(&$gameInfo)
    {
        foreach (['featureDetail', 'bakFeatures', 'jackpotAddition', 'stacks', 'featureTimes', 'gameExtra', 'lastSpinElements'] as $key) {
            if (!key_exists($key, $gameInfo)) continue;
            if ($gameInfo[$key]) {
                $gameInfo[$key] = (array)json_decode($gameInfo[$key], true);
            } else {
                $gameInfo[$key] = array();
            }
        }
    }

    /**
     * 更新玩家游戏信息(增量更新)
     */
    public function updateGameInfo($uid, $machineId, $data, $sync = false)
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $value ? json_encode($value) : '';
            }
        }

        $wheres = array(
            'machineId' => $machineId
        );

        return $this->updateCacheData($uid, $data, $wheres, $sync);
    }
}