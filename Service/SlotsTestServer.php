<?php
/**
 * 机台测试服务
 */

namespace FF\Service;

use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class SlotsTestServer extends PubSubServer
{
    protected $queueName = 'testEnd';

    public function dealMessage($data)
    {
        if (!$data) {
            FF::throwException(Code::FAILED, 'message is null');
        }

        if (empty($data['testId'])) {
            FF::throwException(Code::FAILED, 'testId is null');
        }

        if (empty($data['logPath'])) {
            FF::throwException(Code::FAILED, 'logPath is null');
        }

        $testId = $data['testId'];
        $logPath = $data['logPath'];

        Bll::slotsTest()->onEnded($testId, $logPath);
    }
}