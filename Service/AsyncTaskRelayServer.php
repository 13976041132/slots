<?php
/**
 * 异步任务转发中继站服务
 */

namespace FF\Service;

use FF\Framework\Common\Code;
use FF\Framework\Common\Format;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Service\Lib\RPC;
use GPBClass\Enum\MSG_ID;

class AsyncTaskRelayServer extends PubSubServer
{
    protected $queueName = 'async_task_relay';
    protected $exchange = 'asyncTaskRelay';

    public function dealMessage($data)
    {
        if (!$data) {
            FF::throwException(Code::FAILED, 'message is null');
        }

        if (empty($data['event'])) return;

        $node = null;

        if (isset($data['data']['uid'])) {
            $nodes = Config::get('servers', 'AsyncTask/nodes');
            $node = $data['data']['uid'] % count($nodes);
        }

        RPC::request('AsyncTask', MSG_ID::MSG_ADD_ASYNC_TASK, $data, Format::JSON, true, $node);
    }
}