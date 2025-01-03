<?php
/**
 * 异步任务服务
 */

namespace FF\Service;

use FF\Factory\Bll;
use FF\Framework\Utils\Log;
use FF\Service\Lib\Service;
use Swoole\Timer;

class AsyncTaskServer extends Service
{
    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        if ($server->taskworker) {
            Timer::tick(10 * 1000, function () {
                Bll::asyncTask()->onFlushLogs();
            });
        }
    }

    public function onWorkerStop(\swoole_server $server, $workerId)
    {
        if ($server->taskworker) {
            if (Bll::asyncTask()->getLogBufferInfo()) {
                Bll::asyncTask()->onFlushLogs(true);
            }
        }

        parent::onWorkerStop($server, $workerId);
    }

    protected function dealTask($data)
    {
        if (!$data || empty($data['event'])) {
            return 'Invalid data: ' . json_encode($data);
        }

        Bll::userAdapter()->clearCacheData();

        $event = $data['event'];
        $data = isset($data['data']) ? $data['data'] : array();

        try {
            Bll::asyncTask()->dealTask($event, $data);
        } catch (\Exception $e) {
            Log::error(array(
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ));
        }

        return 'ok';
    }
}