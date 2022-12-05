<?php
/**
 * Tester
 */

namespace FF\Scripts\SlotsTest;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Machines\SlotsModel\SlotsMachine;
use GPBClass\Enum\RET;

class Tester
{
    private $uid;
    private $testId;
    private $runTimes;
    private $machineId;

    /**
     * @var SlotsMachine
     */
    private static $machineObj;

    public function __construct($options)
    {
        $this->uid = $options['uid'];
        $this->testId = $options['testId'];
        $this->runTimes = $options['betTimes'];
        $this->machineId = $options['machineId'];
    }

    public static function getMachineObj()
    {
        return self::$machineObj;
    }

    public function run()
    {
        $k = 0;
        $test = Bll::slotsTest()->getTestInfo($this->testId);
        if (!$test || $test['status'] == 2) return;

        $options = array();
        $options['noFeature'] = !$test['featureOpened'];

        cli_output('Start');

        $machineObj = Bll::machine()->getMachineInstance($this->uid, $this->machineId, 'general');
        self::$machineObj = $machineObj;

        $level = Bll::user()->getField($this->uid, 'level');

        if (!($totalBet = $test['totalBet']) || $level !== $test['userLevel'] && $test['betAutoRaise']) {
            $totalBet = $machineObj->getUnlockMaxBet();
        }

        while (1) {
            try {
                $result = $machineObj->run($totalBet, $options);
                if ($result['cost'] > 0) {
                    $k++;
                    if ($k % 100 == 0) {
                        Dao::redis()->hIncrBy(Keys::slotsTestInfo($this->testId), 'bettedTimes', 100);
                    }
                }
                cli_output($k);
                if ($k >= $this->runTimes && ($result['settled'] || $result['isLastFreeSpin'])) {
                    break;
                }
                //自动提升TotalBet
                if ($test['betMultiple'] && $test['betAutoRaise']) {
                    if ($result['prizes']['levelPrize']['levelUp']) {
                        $totalBet = $machineObj->getUnlockMaxBet();
                    }
                }
            } catch (\Exception $e) {
                if ($e->getCode() != RET::RET_COINS_NOT_ENOUGH) {
                    $error = array(
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    );

                    Dao::redis()->hSet(Keys::slotsTestInfo($this->testId), 'error', json_encode($error));

                    Bll::slotsTest()->onEnded($this->testId);
                    cli_exit($error);
                }
                break;
            }
        }

        if ($k % 100 != 0) {
            Dao::redis()->hIncrBy(Keys::slotsTestInfo($this->testId), 'bettedTimes', $k % 100);
        }
    }
}