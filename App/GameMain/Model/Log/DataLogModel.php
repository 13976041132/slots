<?php
/**
 * 数据改变日志模块
 */

namespace FF\App\GameMain\Model\Log;

use FF\Extend\MyModel;

class DataLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_LOG, 't_data_log');
    }

    public function generate($data)
    {
        return array(
            'uid' => $data['uid'],
            'field' => $data['field'],
            'amount' => $data['amount'],
            'balance' => $data['balance'],
            'reason' => $data['reason'],
            'time' => $data['time'] ?: now()
        );
    }
}