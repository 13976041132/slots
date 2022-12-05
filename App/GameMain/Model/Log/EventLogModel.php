<?php
/**
 * 事件日志模块
 */

namespace FF\App\GameMain\Model\Log;

use FF\Extend\MyModel;

class EventLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_LOG, 't_event_log');
    }

    public function generate($data)
    {
        for ($i = 1; $i <= 8; $i++) {
            $data['extra' . $i] = isset($data['extras'][$i - 1]) ? $data['extras'][$i - 1] : '';
        }
        unset($data['extras']);

        return $data;
    }

    public function generateAdViewData($data)
    {
        return array(
            'uid' => $data['uid'],
            'event' => 'ad_view',
            'extra1' => $data['adType'],
            'extra2' => $data['adPos'],
            'extra3' => $data['all']['publisher_revenue'] ?: '0',
            'extra4' => $data['version'],
            'time' => $data['time'] ?: now()
        );
    }
}