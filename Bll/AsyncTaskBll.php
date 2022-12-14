<?php
/**
 * 异步任务业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\MQ;
use FF\Factory\Sdk;
use FF\Service\Lib\Service;
use FF\Service\Lib\SwooleClient;

class AsyncTaskBll
{
    private $logBuffer = array();

    /**
     * @return SwooleClient
     */
    public function client()
    {
        if (!$this->client) {
            $this->client = new SwooleClient('AsyncTask');
        }
        return $this->client;
    }

    public function addTask($event, $data = array())
    {
        if (!Service::isRunning() && $this->client()->enabled()) {
            $this->client()->send($event, $data);
        } else {
            $this->dealTask($event, $data);
        }
    }

    public function dealTask($event, $data)
    {
        switch ($event) {
            case EVENT_FLUSH_LOGS:
                $this->onFlushLogs();
                break;
            case EVENT_LOGIN:
                $this->onLogin($data);
                break;
            case EVENT_LOGS:
                $this->onLogs($data);
                break;
            case EVENT_PING:
                $this->onPing($data);
                break;

            case EVENT_SETTLEMENT:
                $this->onSettlement($data);
                break;
            case EVENT_USER_STATUS:
                $this->onUserStatus($data);
                break;
            default:
                break;
        }
    }

    /**
     * 处理任务事件
     */
    public function onTaskEvent($data)
    {
        return null;
    }

    public function onLogs($data)
    {
        $logType = $data['type'];
        $logData = $data['logs'];

        if (!Service::isRunning()) {
            Bll::log()->writeLogs($logType, [$logData]);
            return;
        }

        if (!isset($this->logBuffer[$logType])) {
            $this->logBuffer[$logType] = array('list' => array());
        }

        $this->logBuffer[$logType]['list'][] = $logData;
        $this->logBuffer[$logType]['time'] = time();

        $maxBufferSize = $logType === LogBll::LOG_TYPE_BETTING ? 1 : 10;

        if (count($this->logBuffer[$logType]['list']) >= $maxBufferSize) {
            Bll::log()->writeLogs($logType, $this->logBuffer[$logType]['list']);
            $this->logBuffer[$logType]['list'] = array();
            $this->logBuffer[$logType]['time'] = 0;
        }
    }

    public function onFlushLogs($force = false)
    {
        foreach ($this->logBuffer as $logType => $buffer) {
            if ($buffer['list'] && ($buffer['time'] < time() - 10 || $force)) {
                $this->logBuffer[$logType]['list'] = array();
                $this->logBuffer[$logType]['time'] = 0;
                Bll::log()->writeLogs($logType, $buffer['list']);
            }
        }
    }

    public function getLogBufferInfo()
    {
        $bufferInfo = array();

        foreach ($this->logBuffer as $logType => $buffer) {
            if ($buffer['list']) {
                $bufferInfo[$logType]['count'] = count($buffer['list']);
                $bufferInfo[$logType]['time'] = date('Y-m-d H:i:s', $buffer['time']);
            }
        }

        return $bufferInfo;
    }

    public function onSettlement($data)
    {
        if (!$data['betId']) return;

        $this->onFlushLogs(true);
        $message = array(
            'data' => $data,
            'event' => EVENT_SETTLEMENT,
        );

        MQ::rabbitmq()->publish('', json_encode($message), ['routing_key' => 'slotsBettingRes']);
    }

    /**
     * 用户状态变更
     * 上线(online)/离线(offline)/在玩(playing)
     */
    public function onUserStatus($data)
    {
        $uid = $data['uid'];
        $status = $data['status'];
        switch ($status) {
            case 'online':
                Bll::online()->setOnline($uid, $data['loginType']);
                break;
            case 'offline':
                Bll::online()->setOffline($uid);
                break;
            case 'playing':
                $isPlaying = $data['isPlaying'];
                Bll::online()->updateOnline($uid, $isPlaying);
                break;
        }
    }

    public function onLogin($data)
    {
        $uid = $data['uid'];
        $loginType = $data['loginType'];
        $continued = $data['continued'];
        $retention = $data['retention'];
        $version = $data['version'];
        $platform = $data['platform'];
        $country = Sdk::ipip()->getCountry($data['ip']);

        //记录登录事件
        Bll::log()->addEventLog($uid, 'Login', $loginType, $continued, $retention, $version, $country, $platform);

        //破产次日回头与破产当日登录
        if ($data['isBankrupt']) {
            if ($loginType == 2 && $continued > 1) {
                $isNewUser = $retention == 1 ? 1 : 0;
                Bll::log()->addEventLog($uid, 'BankruptBack', $isNewUser);
            } elseif ($loginType == 0) {
                $isNewUser = $retention == 0 ? 1 : 0;
                Bll::log()->addEventLog($uid, 'BankruptLogin', $isNewUser);
            }
        }

        $this->onUserStatus(['uid' => $uid, 'loginType' => $loginType, 'status' => 'online']);
    }

    public function onPing($data)
    {
        $uid = $data['uid'];
        Bll::online()->updateOnline($uid, $data['isPlaying']);
    }
}
