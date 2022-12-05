<?php
/**
 * 日志业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Factory\MQ;
use FF\Framework\Utils\Log;

class LogBll
{
    const LOG_TYPE_DATA_CHANGE = 'DataChange';
    const LOG_TYPE_BETTING = 'Betting';
    const LOG_TYPE_CUSTOMIZE_EVENT = 'CustomizeEvent';

    private $logBuffer = array();

    public function addBetLog($betId, $uid, $machineId, $level, $betSeq, $spinTimes, $betContext, $steps, $extra, $prizes, $settled, $balance, $version, $featureSteps = array())
    {
        $sampleId = $betContext['sampleId'];
        if (isset($betContext['sampleLibId'])) {
            $sampleId = $betContext['sampleLibId'];
        }

        $this->onLogEvent(self::LOG_TYPE_BETTING, array(
            'betId' => $betId,
            'uid' => $uid,
            'machineId' => $machineId,
            'totalSpinTimes' => $betSeq,
            'spinTimes' => $spinTimes,
            'level' => $level,
            'sampleId' => $sampleId,
            'betSeq' => $betSeq,
            'betContext' => $betContext,
            'steps' => $steps,
            'featureSteps' => $featureSteps,
            'extra' => $extra,
            'prizes' => $prizes,
            'settled' => $settled,
            'balance' => $balance,
            'version' => $version,
            'time' => now(),
            'microtime' => microtime(true) * 10000
        ));
    }

    public function addEventLog($uid, $event, ...$extras)
    {
        if (defined('TEST_ID')) return;

        $this->onLogEvent(self::LOG_TYPE_CUSTOMIZE_EVENT, array(
            'uid' => $uid,
            'event' => str_replace('-', '_', $event),
            'extras' => $extras,
            'time' => now(),
        ));
    }

    public function onLogEvent($logType, $data)
    {
        if (defined('TEST_ID')) {
            if (!isset($this->logBuffer[$logType])) {
                $this->logBuffer[$logType] = array();
            }
            $this->logBuffer[$logType][] = $data;
            if (count($this->logBuffer[$logType]) == 20) {
                $this->writeLogs($logType, $this->logBuffer[$logType]);
                $this->logBuffer[$logType] = array();
            }
            return;
        }
        Bll::asyncTask()->addTask(EVENT_LOGS, array(
            'type' => $logType,
            'logs' => $data
        ));
    }

    public function flushLogs()
    {
        foreach ($this->logBuffer as $logType => $logData) {
            if ($logData) {
                $this->writeLogs($logType, $logData);
                unset($this->logBuffer[$logType]);
            }
        }
    }

    public function writeLogs($logType, $logs)
    {
        $logModel = null;

        $messages = array();

        foreach ($logs as $data) {
            $_LogInfo = array();
            switch ($logType) {
                case self::LOG_TYPE_DATA_CHANGE:
                    $logModel = $logModel ?: Model::dataLog();
                    $logData[] = $logModel->generate($data);
                    break;
                case self::LOG_TYPE_BETTING:
                    $logModel = $logModel ?: Model::betLog();
                    $_LogInfo = $logModel->generate($data);
                    break;
                case self::LOG_TYPE_CUSTOMIZE_EVENT:
                    $logModel = $logModel ?: Model::eventLog();
                    $_LogInfo = $logModel->generate($data);
                    break;
                default:
                    break;
            }
            if (!$_LogInfo) continue;

            $messages[] = $_LogInfo;
        }

        if ($messages && (defined('TEST_ID') || $logType !== self::LOG_TYPE_BETTING)) {
            $logModel->insertMulti($messages);
            return;
        }

        foreach ($messages as $message) {
            MQ::rabbitmq()->publish('', json_encode($message), ['routing_key' => 'slotsBettingRes']);
        }
    }

    public function addDataLog($uid, $field, $amount, $balance, $reason)
    {
        if (defined('TEST_ID')) return;

        $this->onLogEvent(self::LOG_TYPE_DATA_CHANGE, array(
            'uid' => $uid,
            'field' => $field,
            'amount' => $amount,
            'balance' => $balance,
            'reason' => $reason,
            'time' => now(),
        ));
    }

}