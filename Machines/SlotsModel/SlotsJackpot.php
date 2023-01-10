<?php
/**
 * 老虎机Jackpot逻辑
 */

namespace FF\Machines\SlotsModel;

use FF\Factory\Bll;
use FF\Library\Utils\Utils;

abstract class SlotsJackpot extends SlotsHitResult
{
    protected $activeJackpots = array();
    protected $jackpotPots = array();
    protected $jackpotProgressAdd = 0;
    protected $jackpotItemCount = 0;

    public function clearBuffer()
    {
        $this->activeJackpots = array();
        $this->jackpotPots = array();
        $this->jackpotProgressAdd = 0;
        $this->jackpotItemCount = 0;

        parent::clearBuffer();
    }

    /**
     * 获取机台jackpot列表
     */
    public function getJackpots()
    {
        return $this->jackpots;
    }

    /**
     * 下注额按比例投入jackpot
     */
    public function updateJackpotAddition()
    {
        if (!$this->jackpots) return;

        foreach ($this->jackpots as $jackpot) {
            if (!(float)$jackpot['betAddition']) continue;
            $addition = round(bcmul($this->betContext['totalBet'], $jackpot['betAddition'], 2));
            if ($jackpot['jackpotType'] == 'Public Jackpot') {
                // 加入到 公共Jackpot 中
                Bll::jackpot()->updatePublicJackpot($this->machineId, $jackpot['jackpotId'], $addition);
            } else {
                // 加入到当前用户数据中
                $this->gameInfo['jackpotAddition'][$jackpot['jackpotName']] += $addition;
            }
        }
    }

    /**
     * 清除下注对jackpot奖金的加成
     */
    public function clearJackpotAddition($jackpotName)
    {
        if (!isset($this->gameInfo['jackpotAddition'][$jackpotName])) return;

        unset($this->gameInfo['jackpotAddition'][$jackpotName]);

        $this->updateGameInfo(array(
            'jackpotAddition' => $this->gameInfo['jackpotAddition']
        ));
    }

    /**
     * 是否有jackpot进度
     */
    protected function hasJackpotProgress()
    {
        return false;
    }

    /**
     * 计算获得的jackpot进度
     */
    protected function calJackpotProgress($prizes)
    {
        return 0;
    }

    /**
     * 获取当前jackpot收集进度
     * 包含本次spin获得的累加进度
     */
    public function getJackpotProgress()
    {
        return $this->gameInfo['jackpotProgress'];
    }

    /**
     * 清除jackpot收集进度
     */
    public function clearJackpotProgress()
    {
        $this->gameInfo['jackpotProgress'] = 0;
    }

    /**
     * 更新jackpot收集进度
     */
    protected function updateJackpotProgress($prizes)
    {
        $progress = $this->calJackpotProgress($prizes);
        if (!$progress) return;

        $this->gameInfo['jackpotProgress'] += $progress;
        $this->jackpotProgressAdd += $progress;
    }

    /**
     * 获取已激活的jackpot
     */
    public function getActiveJackpots()
    {
        if ($this->activeJackpots) {
            return $this->activeJackpots;
        }

        $totalBetLevel = $this->getTotalBetIndex() + 1;
        foreach ($this->jackpots as $jackpotId => $jackpot) {
            $betCnt = count($this->betOptions);
            $activeLevels = array_map(function ($value) use ($betCnt) {
                return ceil($betCnt * $value);
            }, (array)$jackpot['activeLevel']);

            if (Utils::isValueMatched($totalBetLevel, $activeLevels)) {
                $this->activeJackpots[$jackpot['jackpotName']] = $jackpotId;
            }
        }

        return $this->activeJackpots;
    }

    /**
     * 获取jackpot奖池，按jackpot名称索引
     */
    public function getJackpotPots()
    {
        if ($this->jackpotPots) {
            return $this->jackpotPots;
        }

        $totalBet = $this->getTotalBet();

        foreach ($this->getActiveJackpots() as $jackpotName => $jackpotId) {
            $jackpot = $this->jackpots[$jackpotId];
            $pot = Bll::jackpot()->getJackpotPot($jackpot, $totalBet);
            if (!empty($this->gameInfo['jackpotAddition'][$jackpotName])) {
                $pot += $this->gameInfo['jackpotAddition'][$jackpotName];
            }
            $this->jackpotPots[$jackpotName] = array(
                'jackpotId' => $jackpotId,
                'relatedMachineIds' => $jackpot['relatedMachineIds'],
                'pot' => $pot,
            );
        }

        return $this->jackpotPots;
    }

    /**
     * 获取jackpot奖励
     */
    public function getJackpotPrize()
    {
        if (!$this->hasJackpotProgress()) return [];
        if (!$this->jackpotProgressAdd) return [];

        $activeJackpots = $this->getActiveJackpots();
        $jackpotsWithTarget = array();

        //过滤出带收集目标的jackpot
        foreach ($activeJackpots as $jackpotName => $jackpotId) {
            $jackpot = $this->jackpots[$jackpotId];
            if ($jackpot['target']) {
                $jackpotsWithTarget[$jackpot['target']] = $jackpotName;
            }
        }
        ksort($jackpotsWithTarget);

        $prizes = [];
        $finished = false;
        $index = 0;
        $newProgress = $this->getJackpotProgress();
        $oldProgress = $newProgress - $this->jackpotProgressAdd;

        //匹配收集进度，进度满了就给jackpot奖励
        //同时可能获得多个jackpot奖励
        //总进度满了就清空进度，重新累积
        foreach ($jackpotsWithTarget as $target => $jackpotName) {
            $index++;
            if ($oldProgress >= $target) continue; //已经领过了
            if ($newProgress < $target) break; //进度值不够
            $pot = $this->getJackpotAward($jackpotName);
            $prizes[] = array(
                'jackpotId' => $activeJackpots[$jackpotName],
                'jackpotName' => $jackpotName,
                'coins' => $pot
            );
            if ($index == count($jackpotsWithTarget)) {
                $finished = true;
            }
        }

        if ($finished) {
            $this->clearJackpotProgress();
        }

        return $prizes;
    }

    /**
     * 获取jackpot类型[Jackpot|Bonus]
     */
    public function getJackpotType($jackpotName)
    {
        foreach ($this->jackpots as $jackpot) {
            if ($jackpot['jackpotName'] == $jackpotName) {
                return $jackpot['jackpotType'];
            }
        }

        return '';
    }

    /**
     * 领取jackpot奖励，并重置起始时间
     */
    public function getJackpotAward($jackpotName, &$jackpotId = null)
    {
        $jackpotPots = $this->getJackpotPots();

        if (!isset($jackpotPots[$jackpotName])) return 0;

        $jackpotId = $jackpotPots[$jackpotName]['jackpotId'];
        $relatedMachineIds = $jackpotPots[$jackpotName]['relatedMachineIds'];
        $pot = $jackpotPots[$jackpotName]['pot'];

        $this->resetJackpotCreateTime($jackpotName, $relatedMachineIds);
        $this->clearJackpotAddition($jackpotName);

        $this->jackpotPots = array();

        return $pot;
    }

    /**
     * 重置jackpot创建时间
     */
    public function resetJackpotCreateTime($jackpotName, $relatedMachineIds)
    {
        Bll::jackpot()->resetCreateTime($this->machineId, $jackpotName, $relatedMachineIds);

        $now = time();
        foreach ($this->jackpots as &$jackpot) {
            if ($jackpot['jackpotName'] == $jackpotName) {
                $jackpot['createTime'] = $now;
            }
        }
    }
}