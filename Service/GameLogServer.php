<?php
/**
 * 游戏日志服务
 */

namespace FF\Service;

use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use function GuzzleHttp\Promise\promise_for;

class GameLogServer extends PubSubServer
{
    protected $queueName = 'slotsBettingRes';

    public function dealMessage($data)
    {
        if (!$data) {
            FF::throwException(Code::FAILED, 'message is null');
        }

        $event = $data['event'] ?? '';

        switch ($event) {
            case EVENT_SETTLEMENT :
                $this->onSettlement($data['data']);
                break;
            default:
                $this->onLog($data['data'] ?? $data);
                break;
        }

    }

    protected function onLog($data)
    {
        $this->checkData($data);
        Model::betLog()->insert($data);
    }

    protected function onSettlement($data)
    {
        if (empty($data['betId'])) return;

        $betId = $data['betId'];
        unset($data['betId']);

        $this->checkData($data);
        Model::betLog()->updateInfo($betId, $data);
    }

    protected function checkData(&$data)
    {
        foreach ($data as $key => &$value) {
            if (is_bool($value)) {
                $value = (int)$value;
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }
        }
    }

}

